<?php

declare(strict_types=1);

namespace App\Controllers\Fiscal;

use App\Core\Controller;
use App\Core\Menu;
use App\Core\Modulos;
use App\Core\Paginacao;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\Validator;
use App\Core\View;
use App\Exceptions\NaoEncontradoException;
use App\Models\Fiscal\TipoObrigacao;
use App\Services\Fiscal\TipoObrigacaoService;
use PDOException;

final class TipoObrigacaoController extends Controller
{
    private const MENSAGEM_NOME_DUPLICADO = 'Já existe um tipo com este nome neste escritório.';

    private readonly TipoObrigacao $tipos;

    private readonly TipoObrigacaoService $servico;

    public function __construct(View $viewEngine)
    {
        parent::__construct($viewEngine);
        $this->tipos = new TipoObrigacao();
        $this->servico = TipoObrigacaoService::padrao();
    }

    public function index(Request $request): Response
    {
        $filtros = $this->filtrosDaRequisicao($request);
        $pagina = $this->tipos->listar(
            $request->usuario()->escritorioId,
            $filtros,
            Paginacao::deRequest($request, 20)
        );

        return $this->view('fiscal/tipos/index', array_merge(
            $this->contextoLayout($request, 'Tipos de obrigação'),
            [
                'paginaTipos' => $pagina,
                'filtros' => $filtros,
                'filtrosAtivos' => $this->filtrosAtivos($filtros),
                'queryAtual' => $request->todos(),
            ]
        ));
    }

    public function novo(Request $request): Response
    {
        return $this->renderizarFormulario($request, $this->dadosVazios(), [], 200);
    }

    public function criar(Request $request): Response
    {
        $usuario = $request->usuario();
        $dados = $this->dadosDaRequisicao($request);
        $erros = $this->validar($dados);
        if ($erros === [] && $this->servico->nomeJaExiste($usuario, (string) $dados['nome'])) {
            $erros['nome'] = [self::MENSAGEM_NOME_DUPLICADO];
        }
        if ($erros !== []) {
            return $this->renderizarFormulario($request, $dados, $erros, 422);
        }

        try {
            $this->servico->criar($usuario, $this->dadosParaPersistencia($dados));
        } catch (PDOException $erro) {
            // Corrida entre a checagem e o INSERT: a chave única tem a palavra final.
            if (!$this->ehNomeDuplicado($erro)) {
                throw $erro;
            }

            return $this->renderizarFormulario($request, $dados, ['nome' => [self::MENSAGEM_NOME_DUPLICADO]], 422);
        }

        return $this->redirecionar('/tipos-obrigacao', 'Tipo de obrigação cadastrado com sucesso.');
    }

    public function editar(Request $request, int $id): Response
    {
        return $this->renderizarFormulario($request, $this->buscarTipo($request, $id), [], 200, $id);
    }

    public function atualizar(Request $request, int $id): Response
    {
        $usuario = $request->usuario();
        $this->buscarTipo($request, $id);
        $dados = $this->dadosDaRequisicao($request);
        $erros = $this->validar($dados);
        if ($erros === [] && $this->servico->nomeJaExiste($usuario, (string) $dados['nome'], $id)) {
            $erros['nome'] = [self::MENSAGEM_NOME_DUPLICADO];
        }
        if ($erros !== []) {
            return $this->renderizarFormulario($request, $dados, $erros, 422, $id);
        }

        try {
            $this->servico->atualizar($usuario, $id, $this->dadosParaPersistencia($dados));
        } catch (PDOException $erro) {
            if (!$this->ehNomeDuplicado($erro)) {
                throw $erro;
            }

            return $this->renderizarFormulario($request, $dados, ['nome' => [self::MENSAGEM_NOME_DUPLICADO]], 422, $id);
        }

        return $this->redirecionar('/tipos-obrigacao', 'Tipo de obrigação atualizado com sucesso.');
    }

    public function excluir(Request $request, int $id): Response
    {
        $this->servico->excluir($request->usuario(), $id);

        return $this->redirecionar('/tipos-obrigacao', 'Tipo de obrigação excluído com sucesso.');
    }

    /**
     * @return array<string, mixed>
     */
    private function contextoLayout(Request $request, string $titulo): array
    {
        $usuario = $request->usuario();
        $modulos = (new Modulos(new Router()))->carregar();

        return [
            'titulo' => $titulo,
            'usuario' => $usuario,
            'itensMenu' => (new Menu($modulos))->itens($usuario),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buscarTipo(Request $request, int $id): array
    {
        $tipo = $this->tipos->buscar($request->usuario()->escritorioId, $id);
        if ($tipo === null) {
            throw new NaoEncontradoException();
        }

        return $tipo;
    }

    /**
     * @param array<string, mixed> $dados
     * @param array<string, list<string>> $erros
     */
    private function renderizarFormulario(
        Request $request,
        array $dados,
        array $erros,
        int $status,
        ?int $tipoId = null
    ): Response {
        $titulo = $tipoId === null ? 'Novo tipo de obrigação' : 'Editar tipo de obrigação';

        return $this->view('fiscal/tipos/form', array_merge(
            $this->contextoLayout($request, $titulo),
            ['dados' => $dados, 'erros' => $erros, 'tipoId' => $tipoId]
        ), $status);
    }

    /**
     * @return array<string, string>
     */
    private function dadosVazios(): array
    {
        return [
            'nome' => '',
            'descricao' => '',
            'esfera' => 'FEDERAL',
            'periodicidade' => 'MENSAL',
            'dia_vencimento' => '',
            'meses_apos_competencia' => '1',
            'ativo' => '1',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function dadosDaRequisicao(Request $request): array
    {
        $dados = [];
        foreach (array_keys($this->dadosVazios()) as $campo) {
            $valor = $request->input($campo);
            $dados[$campo] = is_scalar($valor) ? trim((string) $valor) : $valor;
        }
        // O formulário envia "0" num campo oculto; a ausência total também significa inativo.
        $dados['ativo'] ??= '0';

        return $dados;
    }

    /**
     * @param array<string, mixed> $dados
     * @return array<string, list<string>>
     */
    private function validar(array $dados): array
    {
        $erros = Validator::validar($dados, [
            'nome' => ['rotulo' => 'Nome', 'regras' => 'obrigatorio|max:100'],
            'descricao' => ['rotulo' => 'Descrição', 'regras' => 'max:255'],
            'esfera' => ['rotulo' => 'Esfera', 'regras' => 'obrigatorio|em:' . implode(',', TipoObrigacao::ESFERAS)],
            'periodicidade' => [
                'rotulo' => 'Periodicidade',
                'regras' => 'obrigatorio|em:' . implode(',', TipoObrigacao::PERIODICIDADES),
            ],
            'dia_vencimento' => ['rotulo' => 'Dia de vencimento', 'regras' => 'inteiro|minimo:1|maximo:31'],
            'meses_apos_competencia' => [
                'rotulo' => 'Meses após a competência',
                'regras' => 'obrigatorio|inteiro|minimo:0|maximo:12',
            ],
            'ativo' => ['rotulo' => 'Ativo', 'regras' => 'em:0,1'],
        ]);

        // O Validator não tem regra condicional; esta espelha o CHECK ck_tipos_dia_obrigatorio.
        $exigeDia = in_array($dados['periodicidade'] ?? null, TipoObrigacao::PERIODICIDADES_COM_DIA, true);
        if ($exigeDia && ($dados['dia_vencimento'] ?? '') === '' && !isset($erros['dia_vencimento'])) {
            $erros['dia_vencimento'] = ['Dia de vencimento é obrigatório para tipos mensais e trimestrais.'];
        }

        return $erros;
    }

    /**
     * @param array<string, mixed> $dados já validados
     * @return array<string, mixed>
     */
    private function dadosParaPersistencia(array $dados): array
    {
        $descricao = is_string($dados['descricao'] ?? null) ? $dados['descricao'] : '';
        $dia = is_string($dados['dia_vencimento'] ?? null) ? $dados['dia_vencimento'] : '';

        return [
            'nome' => (string) $dados['nome'],
            'descricao' => $descricao === '' ? null : $descricao,
            'esfera' => (string) $dados['esfera'],
            'periodicidade' => (string) $dados['periodicidade'],
            'dia_vencimento' => $dia === '' ? null : (int) $dia,
            'meses_apos_competencia' => (int) $dados['meses_apos_competencia'],
            'ativo' => $dados['ativo'] === '1' ? 1 : 0,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function filtrosDaRequisicao(Request $request): array
    {
        $filtros = [];
        foreach (['q', 'esfera', 'periodicidade', 'ativo'] as $campo) {
            $valor = $request->query($campo);
            if (is_string($valor) || is_int($valor)) {
                $filtros[$campo] = (string) $valor;
            }
        }

        return $filtros;
    }

    /**
     * @param array<string, string> $filtros
     * @return array<string, string>
     */
    private function filtrosAtivos(array $filtros): array
    {
        $rotulos = ['q' => 'Busca', 'esfera' => 'Esfera', 'periodicidade' => 'Periodicidade', 'ativo' => 'Situação'];
        $ativos = [];
        foreach ($rotulos as $campo => $rotulo) {
            $valor = $filtros[$campo] ?? '';
            if ($valor !== '') {
                $ativos[$rotulo] = $campo === 'ativo' ? ($valor === '1' ? 'Ativos' : 'Inativos') : $valor;
            }
        }

        return $ativos;
    }

    private function ehNomeDuplicado(PDOException $erro): bool
    {
        $info = is_array($erro->errorInfo) ? $erro->errorInfo : [];

        return (int) ($info[1] ?? 0) === 1062
            && is_string($info[2] ?? null)
            && str_contains($info[2], 'uq_tipos_nome');
    }
}
