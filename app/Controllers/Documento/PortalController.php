<?php

declare(strict_types=1);

namespace App\Controllers\Documento;

use App\Core\Controller;
use App\Core\Menu;
use App\Core\Modulos;
use App\Core\Paginacao;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\Session;
use App\Core\View;
use App\Exceptions\AcessoNegadoException;
use App\Exceptions\NaoEncontradoException;
use App\Models\Cadastro\Empresa;
use App\Models\Documento\SolicitacaoDocumento;
use App\Models\Fiscal\Obrigacao;
use App\Services\Documento\SolicitacaoService;
use App\Services\Documento\UploadService;
use App\Support\Relogio;
use App\Support\RelogioSistema;
use InvalidArgumentException;
use Throwable;

final class PortalController extends Controller
{
    private readonly SolicitacaoDocumento $solicitacoes;
    private readonly Empresa $empresas;
    private readonly Obrigacao $obrigacoes;
    private readonly UploadService $uploadService;
    private readonly Relogio $relogio;

    public function __construct(View $viewEngine)
    {
        parent::__construct($viewEngine);
        $this->solicitacoes = new SolicitacaoDocumento();
        $this->empresas = new Empresa();
        $this->obrigacoes = new Obrigacao();
        $this->uploadService = new UploadService();
        $this->relogio = new RelogioSistema();
    }

    public function index(Request $request): Response
    {
        $usuario = $request->usuario();
        $empresaId = $usuario->empresaId;

        if ($empresaId === null) {
            throw new AcessoNegadoException('Usuário cliente sem empresa vinculada.');
        }

        $empresa = $this->empresas->buscar($usuario->escritorioId, $empresaId);
        if ($empresa === null) {
            throw new NaoEncontradoException('Empresa não encontrada.');
        }

        // Solicitações de documentos da empresa
        $documentos = $this->solicitacoes->listarPorEmpresa($usuario->escritorioId, $empresaId);
        $pendenciasCount = $this->solicitacoes->contarPendenciasEmpresa($usuario->escritorioId, $empresaId);

        // Próximas obrigações/guias da empresa
        $filtrosObrigacoes = [
            'empresa_id' => (string) $empresaId,
            // Guias do portal em ordem cronológica de vencimento (a "prioridade" é só da listagem do escritório).
            'ordem' => 'vencimento',
        ];
        $paginaObrigacoes = $this->obrigacoes->listar($usuario->escritorioId, $filtrosObrigacoes, Paginacao::deRequest($request, 10));

        $dados = array_merge($this->contextoLayout($request, 'Portal da Empresa'), [
            'empresa' => $empresa,
            'documentos' => $documentos,
            'pendenciasCount' => $pendenciasCount,
            'obrigacoes' => $paginaObrigacoes->itens,
            'hoje' => $this->relogio->hoje()->format('Y-m-d'),
        ]);

        return $this->view('portal/index', $dados, 200, 'portal');
    }

    public function enviar(Request $request, int $id): Response
    {
        $usuario = $request->usuario();
        $empresaId = $usuario->empresaId;

        if ($empresaId === null) {
            throw new AcessoNegadoException('Usuário cliente sem empresa vinculada.');
        }

        $solicitacao = $this->solicitacoes->buscar($usuario->escritorioId, $id);
        if ($solicitacao === null || (int) $solicitacao['empresa_id'] !== $empresaId) {
            throw new NaoEncontradoException('Solicitação de documento não encontrada.');
        }

        $arquivo = $request->arquivo('arquivo');
        try {
            $resultado = $this->uploadService->salvarUpload($arquivo);
        } catch (InvalidArgumentException $erro) {
            Session::atual()->flash('erro', $erro->getMessage());
            return Response::redirecionar(url('/portal'));
        } catch (Throwable $erro) {
            Session::atual()->flash('erro', 'Falha ao processar o envio do arquivo. Tente novamente.');
            return Response::redirecionar(url('/portal'));
        }

        $agora = $this->relogio->agora()->format('Y-m-d H:i:s');
        $arquivoDestaRequisicao = $resultado['caminho_relativo'];
        // Grava o arquivo e o evento do histórico na mesma transação. Se nada for confirmado, o service
        // apaga só o arquivo gravado nesta requisição e relança a exceção: transição inválida vira 409
        // (página de conflito do Kernel); qualquer outra falha vira a página genérica de erro.
        SolicitacaoService::padrao()->anexarArquivo(
            $usuario,
            $id,
            $arquivoDestaRequisicao,
            $resultado['nome_original'],
            $agora,
            fn (): bool => $this->uploadService->removerEnvioNaoConfirmado($arquivoDestaRequisicao)
        );

        Session::atual()->flash('sucesso', 'Documento enviado com sucesso! Aguarde a conferência do escritório.');
        return Response::redirecionar(url('/portal'));
    }

    public function download(Request $request, int $id): Response
    {
        $usuario = $request->usuario();
        $empresaId = $usuario->empresaId;

        if ($empresaId === null) {
            throw new AcessoNegadoException('Usuário cliente sem empresa vinculada.');
        }

        $solicitacao = $this->solicitacoes->buscar($usuario->escritorioId, $id);
        if ($solicitacao === null || (int) $solicitacao['empresa_id'] !== $empresaId) {
            throw new NaoEncontradoException('Documento não encontrado.');
        }

        if (empty($solicitacao['arquivo_path'])) {
            throw new NaoEncontradoException('Nenhum arquivo anexado a esta solicitação.');
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
