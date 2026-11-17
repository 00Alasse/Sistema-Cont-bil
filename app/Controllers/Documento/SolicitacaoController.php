<?php

declare(strict_types=1);

namespace App\Controllers\Documento;

use App\Core\Controller;
use App\Core\Menu;
use App\Core\Modulos;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\Validator;
use App\Core\View;
use App\Exceptions\NaoEncontradoException;
use App\Models\Cadastro\Empresa;
use App\Models\Documento\SolicitacaoDocumento;
use App\Services\Documento\UploadService;

final class SolicitacaoController extends Controller
{
    private readonly SolicitacaoDocumento $solicitacoes;
    private readonly Empresa $empresas;
    private readonly UploadService $uploadService;

    public function __construct(View $viewEngine)
    {
        parent::__construct($viewEngine);
        $this->solicitacoes = new SolicitacaoDocumento();
        $this->empresas = new Empresa();
        $this->uploadService = new UploadService();
    }

    public function index(Request $request): Response
    {
        $usuario = $request->usuario();
        $filtros = [
            'empresa_id' => $request->query('empresa_id'),
            'status' => $request->query('status'),
            'busca' => $request->query('q'),
        ];
        $paginacao = \App\Core\Paginacao::deRequest($request, 15);
        $resultado = $this->solicitacoes->listar($usuario->escritorioId, $filtros, $paginacao);
        $empresas = $this->empresas->listarTodas($usuario->escritorioId);

        $dados = array_merge($this->contextoLayout($request, 'Solicitações de Documentos'), [
            'pagina' => $resultado,
            'empresas' => $empresas,
            'filtros' => $filtros,
        ]);

        return $this->view('documentos/solicitacoes/index', $dados);
    }

    public function nova(Request $request): Response
    {
        $usuario = $request->usuario();
        $empresas = $this->empresas->listarTodas($usuario->escritorioId);

        $dados = array_merge($this->contextoLayout($request, 'Nova Solicitação de Documento'), [
            'empresas' => $empresas,
            'antigos' => ['empresa_id' => $request->query('empresa_id', ''), 'prazo' => '', 'titulo' => '', 'descricao' => ''],
            'erros' => [],
        ]);

        return $this->view('documentos/solicitacoes/nova', $dados);
    }

    public function criar(Request $request): Response
    {
        $usuario = $request->usuario();
        $dadosInput = [
            'empresa_id' => $request->input('empresa_id'),
            'titulo' => is_string($request->input('titulo')) ? trim($request->input('titulo')) : '',
            'prazo' => is_string($request->input('prazo')) ? trim($request->input('prazo')) : '',
            'descricao' => is_string($request->input('descricao')) ? trim($request->input('descricao')) : null,
        ];

        $erros = Validator::validar($dadosInput, [
            'empresa_id' => ['rotulo' => 'Empresa', 'regras' => 'obrigatorio|inteiro'],
            'titulo' => ['rotulo' => 'Título', 'regras' => 'obrigatorio|max:150'],
            'prazo' => ['rotulo' => 'Prazo', 'regras' => 'obrigatorio|data'],
        ]);

        $empresaId = (int) $dadosInput['empresa_id'];
        if ($empresaId > 0 && $this->empresas->buscar($usuario->escritorioId, $empresaId) === null) {
            $erros['empresa_id'][] = 'Empresa inválida ou não pertence ao escritório.';
        }

        if ($erros !== []) {
            $empresas = $this->empresas->listarTodas($usuario->escritorioId);
            $dados = array_merge($this->contextoLayout($request, 'Nova Solicitação de Documento'), [
                'empresas' => $empresas,
                'antigos' => $dadosInput,
                'erros' => $erros,
            ]);

            return $this->view('documentos/solicitacoes/nova', $dados, 422);
        }

        $this->solicitacoes->criar($usuario->escritorioId, [
            'empresa_id' => $empresaId,
            'titulo' => $dadosInput['titulo'],
            'descricao' => $dadosInput['descricao'],
            'prazo' => $dadosInput['prazo'],
            'criado_por' => $usuario->id,
        ]);

        return $this->redirecionar('/solicitacoes', 'Solicitação de documento criada com sucesso.');
    }

    public function aceitar(Request $request, int $id): Response
    {
        $usuario = $request->usuario();
        $solicitacao = $this->solicitacoes->buscar($usuario->escritorioId, $id);
        if ($solicitacao === null) {
            throw new NaoEncontradoException('Solicitação não encontrada.');
        }

        $this->solicitacoes->aceitar($usuario->escritorioId, $id);

        return $this->redirecionar('/solicitacoes', 'Documento aceito e conferido.');
    }

    public function recusar(Request $request, int $id): Response
    {
        $usuario = $request->usuario();
        $solicitacao = $this->solicitacoes->buscar($usuario->escritorioId, $id);
        if ($solicitacao === null) {
            throw new NaoEncontradoException('Solicitação não encontrada.');
        }

        $motivo = is_string($request->input('motivo_recusa')) ? trim($request->input('motivo_recusa')) : '';
        if ($motivo === '') {
            $motivo = 'Documento ilegível ou incompleto. Favor enviar novamente.';
        }

        $this->solicitacoes->recusar($usuario->escritorioId, $id, $motivo);

        return $this->redirecionar('/solicitacoes', 'Documento recusado com aviso registrado para o cliente.');
    }

    public function download(Request $request, int $id): Response
    {
        $usuario = $request->usuario();
        $solicitacao = $this->solicitacoes->buscar($usuario->escritorioId, $id);
        if ($solicitacao === null || empty($solicitacao['arquivo_path'])) {
            throw new NaoEncontradoException('Documento não encontrado para download.');
        }

        $arquivoCaminho = $this->uploadService->caminhoAbsoluto((string) $solicitacao['arquivo_path']);
        $nomeOriginal = (string) ($solicitacao['arquivo_nome_original'] ?? 'documento');
        $ext = strtolower(pathinfo($nomeOriginal, PATHINFO_EXTENSION));
        $mimes = [
            'pdf' => 'application/pdf',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'xml' => 'application/xml',
        ];
        $mime = $mimes[$ext] ?? 'application/octet-stream';

        return Response::download($arquivoCaminho, $nomeOriginal, $mime);
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
            'itensMenu' => (new Menu((new Modulos(new Router()))->carregar()))->itens($usuario),
        ];
    }
}
