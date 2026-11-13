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
use App\Core\UsuarioLogado;
use App\Core\Validator;
use App\Core\View;
use App\Exceptions\ConflitoException;
use App\Exceptions\NaoEncontradoException;
use App\Models\Cadastro\Empresa;
use App\Models\Fiscal\Obrigacao;
use App\Services\Fiscal\ObrigacaoService;
use App\Services\Fiscal\ObrigacaoStatusService;
use App\Support\Competencia;
use InvalidArgumentException;
use PDOException;

final class ObrigacaoController extends Controller
{
    public const ROTULOS_STATUS = [
        'PENDENTE' => 'Pendente',
        'EM_ANDAMENTO' => 'Em andamento',
        'AGUARDANDO_CLIENTE' => 'Aguardando cliente',
        'ENTREGUE' => 'Entregue',
        'DISPENSADA' => 'Dispensada',
    ];

    public const ROTULOS_SITUACAO = [
        'atrasada' => 'Atrasadas',
        'vence_hoje' => 'Vencem hoje',
        'vence_7d' => 'Vencem em até 7 dias',
        'entregue_atraso' => 'Entregues com atraso',
    ];

    private const FILTROS = ['q', 'empresa_id', 'tipo_id', 'status', 'situacao', 'responsavel', 'competencia', 'de', 'ate', 'ordem'];

    private const MENSAGEM_ANTES_DA_COMPETENCIA = 'O vencimento não pode ser anterior ao início da competência.';

    private readonly Obrigacao $obrigacoes;

    private readonly Empresa $empresas;

    private readonly ObrigacaoService $servico;

    private readonly ObrigacaoStatusService $servicoStatus;

    public function __construct(View $viewEngine)
    {
        parent::__construct($viewEngine);
        $this->obrigacoes = new Obrigacao();
        $this->empresas = new Empresa();
        $this->servico = ObrigacaoService::padrao();
        $this->servicoStatus = ObrigacaoStatusService::padrao();
    }

    public function index(Request $request): Response
    {
        $usuario = $request->usuario();
        $filtros = $this->filtrosDaRequisicao($request);
        // "meus" vira o ID de quem está logado; o model só entende IDs.
        $filtrosDoModel = $filtros;
        if (($filtros['responsavel'] ?? '') === 'meus') {
            $filtrosDoModel['responsavel'] = (string) $usuario->id;
        }
        $pagina = $this->obrigacoes->listar($usuario->escritorioId, $filtrosDoModel, Paginacao::deRequest($request, 20));
        $opcoes = $this->opcoes($usuario, false);

        return $this->view('fiscal/obrigacoes/index', array_merge(
            $this->contextoLayout($request, 'Obrigações'),
            $opcoes,
            [
                'paginaObrigacoes' => $pagina,
                'filtros' => $filtros,
                'filtrosAtivos' => $this->filtrosAtivos($filtros, $opcoes),
                'queryAtual' => $request->todos(),
                'rotulosStatus' => self::ROTULOS_STATUS,
                'rotulosSituacao' => self::ROTULOS_SITUACAO,
            ]
        ));
    }

    public function nova(Request $request): Response
    {
        $dados = ['empresa_id' => '', 'tipo_obrigacao_id' => '', 'competencia' => '', 'vencimento' => '', 'responsavel_id' => '', 'observacao' => ''];
        $empresa = $request->query('empresa_id');
        if (is_string($empresa) && preg_match('/\A[1-9][0-9]{0,9}\z/', $empresa) === 1) {
            $dados['empresa_id'] = $empresa;
        }

        return $this->renderizarFormulario($request, $dados, [], 200);
    }

    public function criar(Request $request): Response
    {
        $usuario = $request->usuario();
        $dados = $this->dadosDaRequisicao($request, ['empresa_id', 'tipo_obrigacao_id', 'competencia', 'vencimento', 'responsavel_id', 'observacao']);
        $erros = $this->validarCriacao($usuario, $dados);
        if ($erros !== []) {
            $dados = $this->comVencimentoSugerido($usuario, $dados, $erros);

            return $this->renderizarFormulario($request, $dados, $erros, 422);
        }

        try {
            $id = $this->servico->criar($usuario, $this->dadosParaCriacao($dados));
        } catch (PDOException $erro) {
            // Corrida entre a checagem e o INSERT: a chave única tem a palavra final.
            if (!$this->ehDuplicada($erro)) {
                throw $erro;
            }

            return $this->renderizarFormulario($request, $dados, ['competencia' => [ObrigacaoService::MENSAGEM_DUPLICADA]], 422);
        }

        return $this->redirecionar('/obrigacoes/' . $id, 'Obrigação cadastrada com sucesso.');
    }

    public function mostrar(Request $request, int $id): Response
    {
        $obrigacao = $this->buscarObrigacao($request, $id);

        return $this->renderizarDetalhe($request, $obrigacao, $this->formularioStatusVazio($obrigacao), [], 200);
    }

    /**
     * Mudança de status (FI04). Erros de campo → 422 e conflito (versão ou transição) → 409,
     * ambos na própria página de detalhe com o que foi digitado; reabertura sem ser ADMIN → 403.
     */
    public function status(Request $request, int $id): Response
    {
        $usuario = $request->usuario();
        $obrigacao = $this->buscarObrigacao($request, $id);
        $dados = $this->dadosDaRequisicao($request, ['status', 'justificativa', 'protocolo', 'versao']);
        $erros = Validator::validar($dados, [
            'status' => ['rotulo' => 'Novo status', 'regras' => 'obrigatorio|em:' . implode(',', Obrigacao::STATUS)],
            'versao' => ['rotulo' => 'Versão', 'regras' => 'obrigatorio|inteiro'],
        ]);

        if ($erros !== []) {
            return $this->renderizarDetalhe($request, $obrigacao, $dados, $erros, 422);
        }

        $mudanca = [
            'status' => (string) $dados['status'],
            'justificativa' => $this->textoOpcional($dados['justificativa']),
            'protocolo' => $this->textoOpcional($dados['protocolo']),
        ];
        try {
            $erros = $this->servicoStatus->errosDaMudanca($usuario, $obrigacao, $mudanca, (int) $dados['versao']);
            if ($erros !== []) {
                return $this->renderizarDetalhe($request, $obrigacao, $dados, $erros, 422);
            }

            $this->servicoStatus->mudarStatus($usuario, $id, $mudanca, (int) $dados['versao']);
        } catch (ConflitoException $conflito) {
            // A página volta com o estado atual e a versão nova; o texto digitado é preservado.
            $atual = $this->buscarObrigacao($request, $id);
            $dados['versao'] = (string) $atual['versao'];

            return $this->renderizarDetalhe($request, $atual, $dados, [], 409, $conflito->getMessage());
        }

        return $this->redirecionar('/obrigacoes/' . $id, 'Status atualizado.');
    }

    public function editar(Request $request, int $id): Response
    {
        $obrigacao = $this->buscarObrigacao($request, $id);
        $dados = [];
        foreach (['vencimento', 'responsavel_id', 'observacao', 'versao'] as $campo) {
            $dados[$campo] = is_scalar($obrigacao[$campo] ?? null) ? (string) $obrigacao[$campo] : '';
        }

        return $this->renderizarFormulario($request, $dados, [], 200, $obrigacao);
    }

    public function atualizar(Request $request, int $id): Response
    {
        $usuario = $request->usuario();
        $obrigacao = $this->buscarObrigacao($request, $id);
        $dados = $this->dadosDaRequisicao($request, ['vencimento', 'responsavel_id', 'observacao', 'versao']);
        $erros = $this->validarEdicao($usuario, $dados, $obrigacao);
        if ($erros !== []) {
            return $this->renderizarFormulario($request, $dados, $erros, 422, $obrigacao);
        }

        $this->servico->atualizar($usuario, $id, [
            'vencimento' => (string) $dados['vencimento'],
            'responsavel_id' => $this->idOpcional($dados['responsavel_id']),
            'observacao' => $this->textoOpcional($dados['observacao']),
        ], (int) $dados['versao']);

        return $this->redirecionar('/obrigacoes/' . $id, 'Obrigação atualizada com sucesso.');
    }

    public function excluir(Request $request, int $id): Response
    {
        $this->servico->excluir($request->usuario(), $id);

        return $this->redirecionar('/obrigacoes', 'Obrigação excluída com sucesso.');
    }

    /**
     * Formato pelo Validator; depois, só se os campos de referência estiverem bem formados,
     * as checagens no banco (empresa, tipo ativo, responsável, duplicidade).
     *
     * @param array<string, mixed> $dados
     * @return array<string, list<string>>
     */
    private function validarCriacao(UsuarioLogado $usuario, array $dados): array
    {
        $erros = Validator::validar($dados, [
            'empresa_id' => ['rotulo' => 'Empresa', 'regras' => 'obrigatorio|inteiro|minimo:1'],
            'tipo_obrigacao_id' => ['rotulo' => 'Tipo de obrigação', 'regras' => 'obrigatorio|inteiro|minimo:1'],
            'competencia' => ['rotulo' => 'Competência', 'regras' => 'obrigatorio|competencia'],
            'vencimento' => ['rotulo' => 'Vencimento', 'regras' => 'obrigatorio|data'],
            'responsavel_id' => ['rotulo' => 'Responsável', 'regras' => 'inteiro|minimo:1'],
        ]) + $this->errosDaObservacao($dados);

        if (!isset($erros['competencia']) && !isset($erros['vencimento'])) {
            $erros += $this->errosDoVencimento($dados, $this->competenciaOuNull($dados['competencia']));
        }
        $referenciasOk = array_intersect_key($erros, array_flip(['empresa_id', 'tipo_obrigacao_id', 'competencia', 'responsavel_id'])) === [];
        if ($referenciasOk) {
            $erros += $this->servico->errosDaCriacao($usuario, $this->dadosParaCriacao($dados));
        }

        return $erros;
    }

    /**
     * @param array<string, mixed> $dados
     * @param array<string, mixed> $obrigacao
     * @return array<string, list<string>>
     */
    private function validarEdicao(UsuarioLogado $usuario, array $dados, array $obrigacao): array
    {
        $erros = Validator::validar($dados, [
            'vencimento' => ['rotulo' => 'Vencimento', 'regras' => 'obrigatorio|data'],
            'responsavel_id' => ['rotulo' => 'Responsável', 'regras' => 'inteiro|minimo:1'],
            'versao' => ['rotulo' => 'Versão', 'regras' => 'obrigatorio|inteiro|minimo:1'],
        ]) + $this->errosDaObservacao($dados);

        if (!isset($erros['vencimento'])) {
            $erros += $this->errosDoVencimento($dados, Competencia::deData((string) $obrigacao['competencia']));
        }
        if (!isset($erros['responsavel_id'])) {
            $atual = $obrigacao['responsavel_id'] === null ? null : (int) $obrigacao['responsavel_id'];
            $erros += $this->servico->errosDoResponsavel($usuario, $this->idOpcional($dados['responsavel_id']), $atual);
        }

        return $erros;
    }

    /**
     * Espelha o CHECK ck_obr_vencimento (vencimento >= competência).
     *
     * @param array<string, mixed> $dados
     * @return array<string, list<string>>
     */
    private function errosDoVencimento(array $dados, ?Competencia $competencia): array
    {
        $vencimento = is_string($dados['vencimento'] ?? null) ? $dados['vencimento'] : '';
        if ($competencia === null || $vencimento === '' || $vencimento >= $competencia->primeiroDia()->format('Y-m-d')) {
            return [];
        }

        return ['vencimento' => [self::MENSAGEM_ANTES_DA_COMPETENCIA]];
    }

    /**
     * @param array<string, mixed> $dados
     * @return array<string, list<string>>
     */
    private function errosDaObservacao(array $dados): array
    {
        $observacao = $dados['observacao'] ?? null;

        return $observacao === null || is_string($observacao) ? [] : ['observacao' => ['Observação deve ser um texto.']];
    }

    /**
     * Vencimento em branco com tipo e competência válidos: devolve o formulário já com a data
     * calculada (ARCHITECTURE §10.2) para a pessoa conferir. Nada é gravado sem confirmação.
     *
     * @param array<string, mixed> $dados
     * @param array<string, list<string>> $erros
     * @return array<string, mixed>
     */
    private function comVencimentoSugerido(UsuarioLogado $usuario, array $dados, array &$erros): array
    {
        $competencia = $this->competenciaOuNull($dados['competencia']);
        if ($dados['vencimento'] !== '' || $competencia === null || isset($erros['tipo_obrigacao_id'])) {
            return $dados;
        }

        $sugestao = $this->servico->sugerirVencimento($usuario, (int) $dados['tipo_obrigacao_id'], $competencia);
        if ($sugestao !== null) {
            $dados['vencimento'] = $sugestao;
            $erros['vencimento'] = ['Confira o vencimento sugerido a partir do tipo e salve novamente.'];
        }

        return $dados;
    }

    /**
     * @param array<string, mixed> $dados já validados
     * @return array{empresa_id: int, tipo_obrigacao_id: int, competencia: string, vencimento: string, responsavel_id: int|null, observacao: string|null}
     */
    private function dadosParaCriacao(array $dados): array
    {
        $competencia = $this->competenciaOuNull($dados['competencia']);

        return [
            'empresa_id' => (int) $dados['empresa_id'],
            'tipo_obrigacao_id' => (int) $dados['tipo_obrigacao_id'],
            'competencia' => $competencia === null ? '' : $competencia->primeiroDia()->format('Y-m-d'),
            'vencimento' => is_string($dados['vencimento']) ? $dados['vencimento'] : '',
            'responsavel_id' => $this->idOpcional($dados['responsavel_id']),
            'observacao' => $this->textoOpcional($dados['observacao']),
        ];
    }

    /**
     * Só os campos pedidos são lidos: status, competência ou empresa enviados na edição
     * (ou escritorio_id em qualquer formulário) são ignorados.
     *
     * @param list<string> $campos
     * @return array<string, mixed>
     */
    private function dadosDaRequisicao(Request $request, array $campos): array
    {
        $dados = [];
        foreach ($campos as $campo) {
            $valor = $request->input($campo, '');
            $dados[$campo] = is_scalar($valor) ? trim((string) $valor) : $valor;
        }

        return $dados;
    }

    private function competenciaOuNull(mixed $texto): ?Competencia
    {
        try {
            return is_string($texto) ? Competencia::deTexto($texto) : null;
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    private function idOpcional(mixed $valor): ?int
    {
        return is_string($valor) && $valor !== '' ? (int) $valor : null;
    }

    private function textoOpcional(mixed $valor): ?string
    {
        return is_string($valor) && trim($valor) !== '' ? trim($valor) : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function buscarObrigacao(Request $request, int $id): array
    {
        $obrigacao = $this->obrigacoes->buscar($request->usuario()->escritorioId, $id);
        if ($obrigacao === null) {
            throw new NaoEncontradoException();
        }

        return $obrigacao;
    }

    /**
     * @param array<string, mixed> $obrigacao
     * @param array<string, mixed> $dadosStatus valores do formulário de status
     * @param array<string, list<string>> $erros
     */
    private function renderizarDetalhe(
        Request $request,
        array $obrigacao,
        array $dadosStatus,
        array $erros,
        int $status,
        ?string $conflito = null
    ): Response {
        $usuario = $request->usuario();

        return $this->view('fiscal/obrigacoes/show', array_merge(
            $this->contextoLayout($request, 'Detalhes da obrigação'),
            [
                'obrigacao' => $obrigacao,
                'rotulosStatus' => self::ROTULOS_STATUS,
                'historico' => $this->obrigacoes->historico($usuario->escritorioId, (int) $obrigacao['id']),
                'destinos' => ObrigacaoStatusService::destinosPermitidos((string) $obrigacao['status'], $usuario->ehAdmin()),
                'dadosStatus' => $dadosStatus,
                'errosStatus' => $erros,
                'conflitoStatus' => $conflito,
            ]
        ), $status);
    }

    /**
     * @param array<string, mixed> $obrigacao
     * @return array<string, string>
     */
    private function formularioStatusVazio(array $obrigacao): array
    {
        return ['status' => '', 'justificativa' => '', 'protocolo' => '', 'versao' => (string) $obrigacao['versao']];
    }

    /**
     * @param array<string, mixed> $dados
     * @param array<string, list<string>> $erros
     * @param array<string, mixed>|null $obrigacao registro em edição; null na criação
     */
    private function renderizarFormulario(Request $request, array $dados, array $erros, int $status, ?array $obrigacao = null): Response
    {
        $titulo = $obrigacao === null ? 'Nova obrigação' : 'Editar obrigação';

        return $this->view('fiscal/obrigacoes/form', array_merge(
            $this->contextoLayout($request, $titulo),
            $this->opcoes($request->usuario(), true),
            ['dados' => $dados, 'erros' => $erros, 'obrigacao' => $obrigacao]
        ), $status);
    }

    /**
     * Na listagem, o filtro de tipo mostra também os inativos (há obrigações antigas deles);
     * no formulário de criação, só os ativos.
     *
     * @return array{empresas: list<array{id: int, nome: string}>, tipos: list<array{id: int, nome: string}>, responsaveis: list<array{id: int, nome: string, perfil: string}>}
     */
    private function opcoes(UsuarioLogado $usuario, bool $somenteTiposAtivos): array
    {
        return [
            'empresas' => $this->obrigacoes->opcoesEmpresas($usuario->escritorioId),
            'tipos' => $this->obrigacoes->opcoesTipos($usuario->escritorioId, $somenteTiposAtivos),
            'responsaveis' => $this->empresas->listarResponsaveis($usuario->escritorioId),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function filtrosDaRequisicao(Request $request): array
    {
        $filtros = [];
        foreach (self::FILTROS as $campo) {
            $valor = $request->query($campo);
            if (is_string($valor) || is_int($valor)) {
                $filtros[$campo] = trim((string) $valor);
            }
        }

        return $filtros;
    }

    /**
     * Rótulos legíveis para os filtros aplicados (nomes no lugar de IDs).
     *
     * @param array<string, string> $filtros
     * @param array{empresas: list<array{id: int, nome: string}>, tipos: list<array{id: int, nome: string}>, responsaveis: list<array{id: int, nome: string, perfil: string}>} $opcoes
     * @return array<string, string>
     */
    private function filtrosAtivos(array $filtros, array $opcoes): array
    {
        $nomes = [
            'empresa_id' => array_column($opcoes['empresas'], 'nome', 'id'),
            'tipo_id' => array_column($opcoes['tipos'], 'nome', 'id'),
            'responsavel' => ['meus' => 'Meus'] + array_column($opcoes['responsaveis'], 'nome', 'id'),
            'status' => self::ROTULOS_STATUS,
            'situacao' => self::ROTULOS_SITUACAO,
        ];
        $rotulos = [
            'q' => 'Busca', 'empresa_id' => 'Empresa', 'tipo_id' => 'Tipo', 'status' => 'Status',
            'situacao' => 'Situação', 'responsavel' => 'Responsável', 'competencia' => 'Competência',
            'de' => 'Vencimento a partir de', 'ate' => 'Vencimento até', 'ordem' => 'Ordenação',
        ];
        $ativos = [];
        foreach ($rotulos as $campo => $rotulo) {
            $valor = $filtros[$campo] ?? '';
            if ($valor !== '') {
                $ativos[$rotulo] = (string) ($nomes[$campo][$valor] ?? $valor);
            }
        }

        return $ativos;
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

    private function ehDuplicada(PDOException $erro): bool
    {
        $info = is_array($erro->errorInfo) ? $erro->errorInfo : [];

        return (int) ($info[1] ?? 0) === 1062
            && is_string($info[2] ?? null)
            && str_contains($info[2], 'uq_obr_competencia');
    }
}
