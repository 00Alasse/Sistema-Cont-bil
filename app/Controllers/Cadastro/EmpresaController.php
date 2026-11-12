<?php

declare(strict_types=1);

namespace App\Controllers\Cadastro;

use App\Core\Controller;
use App\Core\Menu;
use App\Core\Modulos;
use App\Core\Paginacao;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\UsuarioLogado;
use App\Core\Validator;
use App\Core\View;
use App\Exceptions\NaoEncontradoException;
use App\Models\Cadastro\Empresa;
use App\Services\Cadastro\EmpresaService;
use App\Support\Cnpj;
use PDOException;

final class EmpresaController extends Controller
{
    private readonly Empresa $empresas;

    private readonly EmpresaService $servico;

    public function __construct(
        View $viewEngine
    ) {
        parent::__construct($viewEngine);
        $this->empresas = new Empresa();
        $this->servico = EmpresaService::padrao();
    }

    public function index(Request $request): Response
    {
        $usuario = $request->usuario();
        $filtros = $this->filtrosDaRequisicao($request);
        $pagina = $this->empresas->listar(
            $usuario->escritorioId,
            $filtros,
            Paginacao::deRequest($request, 20)
        );
        $filtrosAtivos = $this->filtrosAtivos($filtros);
        $queryAtual = $request->todos();

        return $this->view('cadastro/empresas/index', array_merge(
            $this->contextoLayout($request, 'Empresas'),
            [
                'paginaEmpresas' => $pagina,
                'filtros' => $filtros,
                'filtrosAtivos' => $filtrosAtivos,
                'responsaveis' => $this->empresas->listarResponsaveis($usuario->escritorioId),
                'queryAtual' => $queryAtual,
            ]
        ));
    }

    public function nova(Request $request): Response
    {
        return $this->renderizarFormulario($request, 'Nova empresa', $this->dadosVazios(), [], 200);
    }

    public function criar(Request $request): Response
    {
        $usuario = $request->usuario();
        $dados = $this->dadosDaRequisicao($request);
        $erros = $this->validarDados($dados);
        if ($erros === []) {
            $erros = $this->validarResponsavel($usuario, $dados);
        }
        $persistencia = $this->dadosParaPersistencia($dados);
        if ($erros === [] && $this->servico->cnpjJaCadastrado($usuario, (string) $persistencia['cnpj'])) {
            $erros['cnpj'] = ['Este CNPJ já está cadastrado neste escritório.'];
        }
        if ($erros !== []) {
            return $this->renderizarFormulario($request, 'Nova empresa', $dados, $erros, 422);
        }

        try {
            $id = $this->servico->criar($usuario, $persistencia);
        } catch (PDOException $erro) {
            if (!$this->ehDuplicidadeDeCnpj($erro)) {
                throw $erro;
            }

            return $this->renderizarFormulario(
                $request,
                'Nova empresa',
                $dados,
                ['cnpj' => ['Este CNPJ já está cadastrado neste escritório.']],
                422
            );
        }

        return $this->redirecionar('/empresas/' . $id, 'Empresa cadastrada com sucesso.');
    }

    public function mostrar(Request $request, int $id): Response
    {
        $empresa = $this->buscarEmpresa($request, $id);
        $modulos = $this->carregarModulos();
        $abas = [];
        foreach ($modulos as $modulo) {
            foreach ($modulo['abas_empresa'] as $aba) {
                $abas[] = $aba;
            }
        }
        usort($abas, static fn (array $a, array $b): int => $a['ordem'] <=> $b['ordem']);

        return $this->view('cadastro/empresas/show', array_merge(
            $this->contextoLayout($request, 'Detalhes da empresa'),
            ['empresa' => $empresa, 'abas' => $abas]
        ));
    }

    public function editar(Request $request, int $id): Response
    {
        $empresa = $this->buscarEmpresa($request, $id);

        return $this->renderizarFormulario(
            $request,
            'Editar empresa',
            $empresa,
            [],
            200,
            $id
        );
    }

    public function atualizar(Request $request, int $id): Response
    {
        $usuario = $request->usuario();
        $this->buscarEmpresa($request, $id);
        $dados = $this->dadosDaRequisicao($request);
        $dados['versao'] = $request->input('versao');
        $erros = $this->validarDados($dados, true);
        if ($erros === []) {
            $erros = $this->validarResponsavel($usuario, $dados);
        }
        $persistencia = $this->dadosParaPersistencia($dados);
        if (
            $erros === []
            && $this->servico->cnpjJaCadastrado($usuario, (string) $persistencia['cnpj'], $id)
        ) {
            $erros['cnpj'] = ['Este CNPJ já está cadastrado neste escritório.'];
        }
        if ($erros !== []) {
            return $this->renderizarFormulario($request, 'Editar empresa', $dados, $erros, 422, $id);
        }

        try {
            $this->servico->atualizar($usuario, $id, $persistencia, (int) $dados['versao']);
        } catch (PDOException $erro) {
            if (!$this->ehDuplicidadeDeCnpj($erro)) {
                throw $erro;
            }

            return $this->renderizarFormulario(
                $request,
                'Editar empresa',
                $dados,
                ['cnpj' => ['Este CNPJ já está cadastrado neste escritório.']],
                422,
                $id
            );
        }

        return $this->redirecionar('/empresas/' . $id, 'Empresa atualizada com sucesso.');
    }

    public function excluir(Request $request, int $id): Response
    {
        $this->servico->excluir($request->usuario(), $id);

        return $this->redirecionar('/empresas', 'Empresa excluída com sucesso.');
    }

    /**
     * @return array<string, mixed>
     */
    private function contextoLayout(Request $request, string $titulo): array
    {
        $usuario = $request->usuario();

        return [
            'titulo' => $titulo,
            'usuario' => $usuario,
            'itensMenu' => (new Menu($this->carregarModulos()))->itens($usuario),
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function carregarModulos(): array
    {
        return (new Modulos(new Router()))->carregar();
    }

    /**
     * @return array<string, mixed>
     */
    private function buscarEmpresa(Request $request, int $id): array
    {
        $empresa = $this->empresas->buscar($request->usuario()->escritorioId, $id);
        if ($empresa === null) {
            throw new NaoEncontradoException();
        }

        return $empresa;
    }

    /**
     * @param array<string, mixed> $dados
     * @param array<string, list<string>> $erros
     */
    private function renderizarFormulario(
        Request $request,
        string $titulo,
        array $dados,
        array $erros,
        int $status,
        ?int $empresaId = null
    ): Response {
        $usuario = $request->usuario();

        return $this->view('cadastro/empresas/form', array_merge(
            $this->contextoLayout($request, $titulo),
            [
                'dados' => $dados,
                'erros' => $erros,
                'empresaId' => $empresaId,
                'responsaveis' => $this->empresas->listarResponsaveis($usuario->escritorioId),
            ],
        ), $status);
    }

    /**
     * @return array<string, mixed>
     */
    private function dadosVazios(): array
    {
        return [
            'razao_social' => '',
            'nome_fantasia' => '',
            'cnpj' => '',
            'regime_tributario' => 'SIMPLES_NACIONAL',
            'segmento' => '',
            'contato_nome' => '',
            'email' => '',
            'telefone' => '',
            'responsavel_id' => '',
            'bpo_ativo' => '0',
            'consultivo_ativo' => '0',
            'observacoes' => '',
            'versao' => '',
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
        foreach (['bpo_ativo', 'consultivo_ativo'] as $campo) {
            $dados[$campo] = $dados[$campo] === null || $dados[$campo] === ''
                ? '0'
                : $dados[$campo];
        }

        return $dados;
    }

    /**
     * @param array<string, mixed> $dados
     * @return array<string, list<string>>
     */
    private function validarDados(array $dados, bool $edicao = false): array
    {
        $regras = [
            'razao_social' => ['rotulo' => 'Razão social', 'regras' => 'obrigatorio|max:150'],
            'nome_fantasia' => ['rotulo' => 'Nome fantasia', 'regras' => 'max:150'],
            'cnpj' => ['rotulo' => 'CNPJ', 'regras' => 'obrigatorio|cnpj'],
            'regime_tributario' => [
                'rotulo' => 'Regime tributário',
                'regras' => 'obrigatorio|em:MEI,SIMPLES_NACIONAL,LUCRO_PRESUMIDO,LUCRO_REAL',
            ],
            'segmento' => ['rotulo' => 'Segmento', 'regras' => 'max:80'],
            'contato_nome' => ['rotulo' => 'Contato', 'regras' => 'max:120'],
            'email' => ['rotulo' => 'E-mail', 'regras' => 'email|max:150'],
            'telefone' => ['rotulo' => 'Telefone', 'regras' => 'max:20'],
            'responsavel_id' => ['rotulo' => 'Responsável', 'regras' => 'inteiro|minimo:1'],
            'bpo_ativo' => ['rotulo' => 'BPO ativo', 'regras' => 'em:0,1'],
            'consultivo_ativo' => ['rotulo' => 'Consultivo ativo', 'regras' => 'em:0,1'],
        ];
        if ($edicao) {
            $regras['versao'] = ['rotulo' => 'Versão', 'regras' => 'obrigatorio|inteiro|minimo:1'];
        }

        $erros = Validator::validar($dados, $regras);
        $observacoes = $dados['observacoes'] ?? null;
        if ($observacoes !== null && !is_string($observacoes)) {
            $erros['observacoes'] = ['Observações deve ser um texto.'];
        }

        return $erros;
    }

    /**
     * @param array<string, mixed> $dados
     * @return array<string, list<string>>
     */
    private function validarResponsavel(UsuarioLogado $usuario, array $dados): array
    {
        $responsavel = $dados['responsavel_id'] ?? '';
        if (!is_string($responsavel) || $responsavel === '') {
            return [];
        }

        if ($this->servico->responsavelValido($usuario, (int) $responsavel)) {
            return [];
        }

        return ['responsavel_id' => ['Selecione um responsável ativo deste escritório.']];
    }

    /**
     * @param array<string, mixed> $dados
     * @return array<string, mixed>
     */
    private function dadosParaPersistencia(array $dados): array
    {
        return [
            'razao_social' => is_string($dados['razao_social'] ?? null) ? trim($dados['razao_social']) : '',
            'nome_fantasia' => $this->textoOpcional($dados, 'nome_fantasia'),
            'cnpj' => Cnpj::normalizar(is_string($dados['cnpj'] ?? null) ? $dados['cnpj'] : ''),
            'regime_tributario' => is_string($dados['regime_tributario'] ?? null)
                ? $dados['regime_tributario']
                : '',
            'segmento' => $this->textoOpcional($dados, 'segmento'),
            'contato_nome' => $this->textoOpcional($dados, 'contato_nome'),
            'email' => $this->textoOpcional($dados, 'email'),
            'telefone' => $this->textoOpcional($dados, 'telefone'),
            'responsavel_id' => $this->responsavelOpcional($dados),
            'bpo_ativo' => ($dados['bpo_ativo'] ?? '0') === '1' ? 1 : 0,
            'consultivo_ativo' => ($dados['consultivo_ativo'] ?? '0') === '1' ? 1 : 0,
            'observacoes' => $this->textoOpcional($dados, 'observacoes'),
        ];
    }

    /**
     * @param array<string, mixed> $dados
     */
    private function textoOpcional(array $dados, string $campo): ?string
    {
        $valor = $dados[$campo] ?? null;

        return is_string($valor) && trim($valor) !== '' ? trim($valor) : null;
    }

    /**
     * @param array<string, mixed> $dados
     */
    private function responsavelOpcional(array $dados): ?int
    {
        $valor = $dados['responsavel_id'] ?? null;

        return is_string($valor) && $valor !== '' ? (int) $valor : null;
    }

    /**
     * @param array<string, mixed> $filtros
     * @return array<string, string>
     */
    private function filtrosAtivos(array $filtros): array
    {
        $rotulos = [
            'q' => 'Busca',
            'status' => 'Status',
            'regime' => 'Regime tributário',
            'responsavel_id' => 'Responsável',
            'servico' => 'Serviço',
            'ordem' => 'Ordenação',
        ];
        $ativos = [];
        foreach ($rotulos as $campo => $rotulo) {
            $valor = $filtros[$campo] ?? '';
            if (is_string($valor) && $valor !== '') {
                $ativos[$rotulo] = $valor;
            }
        }

        return $ativos;
    }

    /**
     * @return array<string, string>
     */
    private function filtrosDaRequisicao(Request $request): array
    {
        $filtros = [];
        foreach (['q', 'status', 'regime', 'responsavel_id', 'servico', 'ordem'] as $campo) {
            $valor = $request->query($campo);
            if (is_string($valor) || is_int($valor)) {
                $filtros[$campo] = (string) $valor;
            }
        }

        return $filtros;
    }

    private function ehDuplicidadeDeCnpj(PDOException $erro): bool
    {
        $info = is_array($erro->errorInfo) ? $erro->errorInfo : [];
        $codigoSql = $info[1] ?? null;
        $mensagemSql = $info[2] ?? null;

        return is_numeric($codigoSql)
            && (int) $codigoSql === 1062
            && is_string($mensagemSql)
            && str_contains($mensagemSql, 'uq_empresas_cnpj_por_escritorio');
    }
}
