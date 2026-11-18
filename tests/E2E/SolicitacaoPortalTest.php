<?php

declare(strict_types=1);

namespace Tests\E2E;

use DateTimeImmutable;
use Tests\Support\ClienteHttp;
use Tests\Support\RespostaHttp;

/**
 * Fluxo de documentos pelo HTTP: o escritório pede, o cliente envia pelo portal, o escritório confere.
 *
 * Roda só no banco tributo_test (E2ETestCase). Tudo o que o teste cria (linhas e arquivos em
 * storage/uploads) é apagado no tearDown para não contaminar os outros testes nem o armazenamento local.
 *
 * Os testes "NaoUsa...Inline" descrevem o comportamento esperado depois da reconciliação com a ADR-024
 * (CSP sem estilo nem handler em linha) e falham enquanto as views antigas continuarem como estão.
 */
final class SolicitacaoPortalTest extends E2ETestCase
{
    private const SENHA = 'Senha@123';
    private const PDF = "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF\n";
    private const LIMITE_BYTES = 5 * 1024 * 1024;

    /** Empresas do seed do escritório 1: cliente.padaria é da 1; cliente.oficina, da 2. */
    private const EMPRESA_PADARIA = 1;
    private const EMPRESA_OFICINA = 2;

    /** @var list<int> */
    private array $criadas = [];

    /** @var list<string> */
    private array $temporarios = [];

    protected function tearDown(): void
    {
        $this->limparCriados();
        parent::tearDown();
    }

    public function testListagemENovaSolicitacaoAbremParaOEscritorio(): void
    {
        // Regressão: as duas telas davam HTTP 500 (consulta com colunas inexistentes).
        $admin = $this->entrar('admin@exemplo.test');

        $lista = $admin->get('/solicitacoes');
        self::assertSame(200, $lista->status);
        self::assertStringContainsString('Solicitações de Documentos', $lista->corpo);

        $nova = $admin->get('/solicitacoes/nova');
        self::assertSame(200, $nova->status);
        self::assertStringContainsString('Padaria Pão da Praça Ltda.', $nova->corpo);
    }

    public function testCriarSolicitacaoValidaAparecaNaListagemENoPortalDaEmpresa(): void
    {
        $admin = $this->entrar('admin@exemplo.test');
        $id = $this->criarSolicitacao($admin, self::EMPRESA_PADARIA, 'E2E Extrato de outubro');

        self::assertSame('aberta', $this->coluna($id, 'status'));
        self::assertStringContainsString('E2E Extrato de outubro', $admin->get('/solicitacoes')->corpo);

        $portal = $this->entrar('cliente.padaria@exemplo.test')->get('/portal');
        self::assertSame(200, $portal->status);
        self::assertStringContainsString('E2E Extrato de outubro', $portal->corpo);
    }

    public function testCriarSolicitacaoInvalidaMostraErroPorCampo(): void
    {
        $admin = $this->entrar('admin@exemplo.test');
        $resposta = $this->postarFormulario($admin, '/solicitacoes/nova', '/solicitacoes', [
            'empresa_id' => '',
            'titulo' => '',
            'prazo' => '',
            'descricao' => '',
        ]);

        self::assertSame(422, $resposta->status);
        self::assertStringContainsString('Empresa é obrigatório.', $resposta->corpo);
        self::assertStringContainsString('Título é obrigatório.', $resposta->corpo);
        self::assertStringContainsString('Prazo é obrigatório.', $resposta->corpo);
    }

    public function testSolicitacaoDeUmaEmpresaNaoApareceNoPortalDeOutra(): void
    {
        $admin = $this->entrar('admin@exemplo.test');
        $this->criarSolicitacao($admin, self::EMPRESA_OFICINA, 'E2E Somente da oficina');

        self::assertStringContainsString(
            'E2E Somente da oficina',
            $this->entrar('cliente.oficina@exemplo.test')->get('/portal')->corpo
        );
        self::assertStringNotContainsString(
            'E2E Somente da oficina',
            $this->entrar('cliente.padaria@exemplo.test')->get('/portal')->corpo
        );
    }

    public function testClienteEnviaOArquivoEOEscritorioBaixaEAceita(): void
    {
        $admin = $this->entrar('admin@exemplo.test');
        $cliente = $this->entrar('cliente.padaria@exemplo.test');
        $id = $this->criarSolicitacao($admin, self::EMPRESA_PADARIA, 'E2E Fluxo completo');

        $envio = $this->enviarArquivo($cliente, $id, 'extrato.pdf', self::PDF, 'application/pdf');
        self::assertSame(302, $envio->status);
        self::assertSame('/portal', $envio->location());
        self::assertSame('enviada', $this->coluna($id, 'status'));
        self::assertSame('extrato.pdf', $this->coluna($id, 'arquivo_nome_original'));

        $doCliente = $cliente->get('/portal/documentos/' . $id . '/download');
        self::assertSame(200, $doCliente->status);
        self::assertSame(self::PDF, $doCliente->corpo);
        $doEscritorio = $admin->get('/solicitacoes/' . $id . '/download');
        self::assertSame(200, $doEscritorio->status);
        self::assertSame(self::PDF, $doEscritorio->corpo);

        $aceite = $admin->post('/solicitacoes/' . $id . '/aceitar', ['_csrf' => $this->token($admin, '/solicitacoes')]);
        self::assertSame(302, $aceite->status);
        self::assertSame('aceita', $this->coluna($id, 'status'));
    }

    public function testRecusaGuardaOMotivoEOPortalOMostraEscapado(): void
    {
        $admin = $this->entrar('admin@exemplo.test');
        $cliente = $this->entrar('cliente.padaria@exemplo.test');
        $id = $this->criarSolicitacao($admin, self::EMPRESA_PADARIA, 'E2E Recusa com motivo');
        $this->enviarArquivo($cliente, $id, 'extrato.pdf', self::PDF, 'application/pdf');

        $resposta = $admin->post('/solicitacoes/' . $id . '/recusar', [
            '_csrf' => $this->token($admin, '/solicitacoes'),
            'motivo_recusa' => 'Ilegível <b>E2E</b>',
        ]);

        self::assertSame(302, $resposta->status);
        self::assertSame('recusada', $this->coluna($id, 'status'));
        self::assertSame('Ilegível <b>E2E</b>', $this->coluna($id, 'motivo_recusa'));
        $portal = $cliente->get('/portal');
        self::assertStringContainsString('Ilegível &lt;b&gt;E2E&lt;/b&gt;', $portal->corpo);
        self::assertStringNotContainsString('<b>E2E</b>', $portal->corpo);
    }

    public function testRecusaSemMotivoUsaAMensagemPadrao(): void
    {
        $admin = $this->entrar('admin@exemplo.test');
        $cliente = $this->entrar('cliente.padaria@exemplo.test');
        $id = $this->criarSolicitacao($admin, self::EMPRESA_PADARIA, 'E2E Recusa sem motivo');
        $this->enviarArquivo($cliente, $id, 'extrato.pdf', self::PDF, 'application/pdf');

        $admin->post('/solicitacoes/' . $id . '/recusar', [
            '_csrf' => $this->token($admin, '/solicitacoes'),
            'motivo_recusa' => '   ',
        ]);

        self::assertSame('recusada', $this->coluna($id, 'status'));
        self::assertSame('Documento ilegível ou incompleto. Favor enviar novamente.', $this->coluna($id, 'motivo_recusa'));
    }

    public function testArquivosInvalidosSaoRejeitadosESolicitacaoContinuaAberta(): void
    {
        $admin = $this->entrar('admin@exemplo.test');
        $cliente = $this->entrar('cliente.padaria@exemplo.test');
        $id = $this->criarSolicitacao($admin, self::EMPRESA_PADARIA, 'E2E Arquivos inválidos');

        $invalidos = [
            'extensão fora da lista' => ['nota.txt', 'texto simples', 'text/plain'],
            'conteúdo que não é PDF' => ['falso.pdf', 'MZ conteúdo executável', 'application/pdf'],
            'acima do limite de 5 MB' => ['grande.pdf', self::PDF . str_repeat('0', self::LIMITE_BYTES), 'application/pdf'],
        ];
        foreach ($invalidos as $motivo => [$nome, $conteudo, $mime]) {
            $resposta = $this->enviarArquivo($cliente, $id, $nome, $conteudo, $mime);

            self::assertSame(302, $resposta->status, $motivo);
            self::assertSame('/portal', $resposta->location(), $motivo);
            self::assertSame('aberta', $this->coluna($id, 'status'), $motivo);
            self::assertNull($this->colunaOuNulo($id, 'arquivo_path'), $motivo);
        }
    }

    public function testIsolamentoEntreEscritoriosEEmpresas(): void
    {
        $admin = $this->entrar('admin@exemplo.test');
        $cliente = $this->entrar('cliente.padaria@exemplo.test');
        $id = $this->criarSolicitacao($admin, self::EMPRESA_PADARIA, 'E2E Isolamento');
        $this->enviarArquivo($cliente, $id, 'extrato.pdf', self::PDF, 'application/pdf');

        // Outro escritório: 404 (nunca revela que o ID existe).
        $outroEscritorio = $this->entrar('admin@modelo.test');
        self::assertSame(404, $outroEscritorio->get('/solicitacoes/' . $id . '/download')->status);
        self::assertSame(
            404,
            $outroEscritorio->post('/solicitacoes/' . $id . '/aceitar', ['_csrf' => $this->token($outroEscritorio, '/solicitacoes')])->status
        );
        $clienteOutroEscritorio = $this->entrar('cliente@modelo.test');
        self::assertSame(404, $clienteOutroEscritorio->get('/portal/documentos/' . $id . '/download')->status);

        // Mesmo escritório, outra empresa: o cliente da oficina não vê nem mexe no documento da padaria.
        $oficina = $this->entrar('cliente.oficina@exemplo.test');
        self::assertSame(404, $oficina->get('/portal/documentos/' . $id . '/download')->status);
        $envio = $this->enviarArquivo($oficina, $id, 'intruso.pdf', self::PDF, 'application/pdf');
        self::assertSame(404, $envio->status);
        self::assertSame('extrato.pdf', $this->coluna($id, 'arquivo_nome_original'));
        self::assertSame('enviada', $this->coluna($id, 'status'));
    }

    public function testPerfisSemPermissaoRecebem403(): void
    {
        $cliente = $this->entrar('cliente.padaria@exemplo.test');
        $token = $this->token($cliente, '/portal');

        self::assertSame(403, $cliente->get('/solicitacoes')->status);
        self::assertSame(403, $cliente->get('/solicitacoes/nova')->status);
        self::assertSame(403, $cliente->post('/solicitacoes/1/aceitar', ['_csrf' => $token])->status);
        self::assertSame(403, $this->entrar('admin@exemplo.test')->get('/portal')->status);
    }

    public function testPostSemTokenCsrfRetorna419(): void
    {
        $admin = $this->entrar('admin@exemplo.test');
        $id = $this->criarSolicitacao($admin, self::EMPRESA_PADARIA, 'E2E Sem CSRF');

        self::assertSame(419, $admin->post('/solicitacoes/' . $id . '/aceitar', [])->status);
        self::assertSame(419, $admin->post('/solicitacoes/' . $id . '/recusar', ['motivo_recusa' => 'x'])->status);
        self::assertSame(419, $admin->post('/solicitacoes', ['empresa_id' => '1', 'titulo' => 'x', 'prazo' => '2030-01-01'])->status);
    }

    // ---- Esperado depois da reconciliação com a ADR-024 (CSP: sem style= nem handlers em linha) ----

    public function testListagemComDocumentoEnviadoNaoUsaEstiloNemHandlerInline(): void
    {
        $admin = $this->entrar('admin@exemplo.test');
        $cliente = $this->entrar('cliente.padaria@exemplo.test');
        $id = $this->criarSolicitacao($admin, self::EMPRESA_PADARIA, 'E2E Marcação da listagem');
        $this->enviarArquivo($cliente, $id, 'extrato.pdf', self::PDF, 'application/pdf');

        $corpo = $admin->get('/solicitacoes')->corpo;

        self::assertStringContainsString('/solicitacoes/' . $id . '/aceitar', $corpo);
        self::assertStringContainsString('/solicitacoes/' . $id . '/recusar', $corpo);
        self::assertDoesNotMatchRegularExpression('/\sstyle\s*=/i', $corpo, 'style= bloqueado pela CSP');
        self::assertDoesNotMatchRegularExpression('/\son[a-z]+\s*=/i', $corpo, 'handler inline bloqueado pela CSP');
        // A confirmação do aceite passa pelo mecanismo do app.js, não por onsubmit.
        self::assertMatchesRegularExpression(
            '#<form[^>]*action="/solicitacoes/' . $id . '/aceitar"[^>]*\sdata-confirmar="[^"]+"#',
            $corpo
        );
    }

    public function testRecusaAbreEmModalBootstrapComMotivoObrigatorioECancelamentoSemEnvio(): void
    {
        $admin = $this->entrar('admin@exemplo.test');
        $cliente = $this->entrar('cliente.padaria@exemplo.test');
        $id = $this->criarSolicitacao($admin, self::EMPRESA_PADARIA, 'E2E Modal de recusa');
        $this->enviarArquivo($cliente, $id, 'extrato.pdf', self::PDF, 'application/pdf');

        $corpo = $admin->get('/solicitacoes')->corpo;

        // O botão da linha só abre o modal (type="button"): não envia nada por si só.
        self::assertMatchesRegularExpression(
            '#<button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="\#modal-recusa-' . $id . '">#',
            $corpo
        );
        // O modal é acessível (título ligado por aria-labelledby) e o formulário mantém rota, método e CSRF.
        self::assertStringContainsString('id="modal-recusa-' . $id . '" tabindex="-1" aria-labelledby="titulo-modal-recusa-' . $id . '"', $corpo);
        self::assertStringContainsString('id="titulo-modal-recusa-' . $id . '"', $corpo);
        self::assertMatchesRegularExpression(
            '#<form class="modal-content" method="post" action="/solicitacoes/' . $id . '/recusar">\s*<input type="hidden" name="_csrf"#',
            $corpo
        );
        // Motivo obrigatório, com rótulo associado.
        self::assertMatchesRegularExpression('#<label[^>]*for="motivo_' . $id . '">Motivo da recusa:</label>#', $corpo);
        self::assertMatchesRegularExpression('#<textarea[^>]*id="motivo_' . $id . '" name="motivo_recusa"[^>]*\srequired#', $corpo);
        // Cancelar e X fecham o modal sem enviar (type="button" com data-bs-dismiss); só "Confirmar" envia.
        self::assertStringContainsString('<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>', $corpo);
        self::assertStringContainsString('<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>', $corpo);
        self::assertStringContainsString('<button type="submit" class="btn btn-danger">Confirmar Recusa</button>', $corpo);
        // Abrir a listagem não recusa nada.
        self::assertSame('enviada', $this->coluna($id, 'status'));
    }

    // Testes HTTP/HTML + leitura do CSS: provam a marcação e as regras que escondem/mostram cada caminho.
    // Eles NÃO executam JavaScript nem renderizam a página; o comportamento visual foi conferido à parte no navegador.

    public function testRecusaSemJsTemFormularioSimplesComMesmaRotaCampoECsrf(): void
    {
        $admin = $this->entrar('admin@exemplo.test');
        $cliente = $this->entrar('cliente.padaria@exemplo.test');
        $id = $this->criarSolicitacao($admin, self::EMPRESA_PADARIA, 'E2E Recusa sem JS');
        $this->enviarArquivo($cliente, $id, 'extrato.pdf', self::PDF, 'application/pdf');

        $corpo = $admin->get('/solicitacoes')->corpo;

        // <details> nativo: abre sem JS e contém o formulário completo.
        self::assertMatchesRegularExpression(
            '#<details class="recusa-sem-js[^"]*">\s*<summary[^>]*>[^<]*Recusar</summary>\s*<form[^>]*method="post" action="/solicitacoes/' . $id . '/recusar">\s*<input type="hidden" name="_csrf"#',
            $corpo
        );
        self::assertMatchesRegularExpression('#<label[^>]*for="motivo-simples-' . $id . '">#', $corpo);
        self::assertMatchesRegularExpression('#<textarea[^>]*id="motivo-simples-' . $id . '" name="motivo_recusa"[^>]*\srequired#', $corpo);
        self::assertStringContainsString('Confirmar Recusa</button>', $corpo);
        // O modal continua na página para quem tem JS.
        self::assertStringContainsString('id="modal-recusa-' . $id . '"', $corpo);
        self::assertDoesNotMatchRegularExpression('/\sstyle\s*=/i', $corpo);
        self::assertDoesNotMatchRegularExpression('/\son[a-z]+\s*=/i', $corpo);
    }

    public function testEnvioDoFormularioSemJsRecusaComOMotivoInformado(): void
    {
        $admin = $this->entrar('admin@exemplo.test');
        $cliente = $this->entrar('cliente.padaria@exemplo.test');
        $id = $this->criarSolicitacao($admin, self::EMPRESA_PADARIA, 'E2E Envio recusa sem JS');
        $this->enviarArquivo($cliente, $id, 'extrato.pdf', self::PDF, 'application/pdf');

        // Usa exatamente o que o formulário sem JS envia: o token do próprio <details> e o campo motivo_recusa.
        $corpo = $admin->get('/solicitacoes')->corpo;
        self::assertSame(1, preg_match(
            '#<details class="recusa-sem-js.*?action="/solicitacoes/' . $id . '/recusar">\s*<input type="hidden" name="_csrf" value="([^"]+)"#s',
            $corpo,
            $m
        ));

        $resposta = $admin->post('/solicitacoes/' . $id . '/recusar', ['_csrf' => $m[1], 'motivo_recusa' => 'Sem JS: faltou a assinatura']);

        self::assertSame(302, $resposta->status);
        self::assertSame('recusada', $this->coluna($id, 'status'));
        self::assertSame('Sem JS: faltou a assinatura', $this->coluna($id, 'motivo_recusa'));
    }

    public function testCssAlternaEntreModalComJsEDetailsSemJs(): void
    {
        $css = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/app.css');

        // Sem JS, o botão que só abre o modal some (não fica controle inoperante)...
        self::assertMatchesRegularExpression('/html:not\(\.js\) \[data-bs-toggle="modal"\]\s*\{\s*display:\s*none\s*!important/', $css);
        // ...e com JS o formulário simples some, deixando só o modal.
        self::assertMatchesRegularExpression('/\.js \.recusa-sem-js\s*\{\s*display:\s*none/', $css);
    }

    public function testAppJsMarcaAPaginaComAClasseJsQueAlternaOsDoisCaminhos(): void
    {
        $js = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/app.js');

        self::assertStringContainsString('document.documentElement.classList.add("js")', $js);
    }

    public function testPortalComPendenciaAtrasadaEDocumentoRecusadoNaoUsaEstiloNemHandlerInline(): void
    {
        $admin = $this->entrar('admin@exemplo.test');
        $cliente = $this->entrar('cliente.padaria@exemplo.test');
        $dataPassada = (new DateTimeImmutable('-3 days'))->format('Y-m-d');
        $atrasada = $this->criarSolicitacao($admin, self::EMPRESA_PADARIA, 'E2E Pendência atrasada', $dataPassada);
        $recusada = $this->criarSolicitacao($admin, self::EMPRESA_PADARIA, 'E2E Documento recusado');
        $this->enviarArquivo($cliente, $recusada, 'extrato.pdf', self::PDF, 'application/pdf');
        $admin->post('/solicitacoes/' . $recusada . '/recusar', [
            '_csrf' => $this->token($admin, '/solicitacoes'),
            'motivo_recusa' => 'Faltou a última página',
        ]);

        $corpo = $cliente->get('/portal')->corpo;

        self::assertStringContainsString('E2E Pendência atrasada', $corpo);
        self::assertStringContainsString('Faltou a última página', $corpo);
        self::assertStringContainsString('/portal/documentos/' . $atrasada . '/enviar', $corpo);
        self::assertDoesNotMatchRegularExpression('/\sstyle\s*=/i', $corpo, 'style= bloqueado pela CSP');
        self::assertDoesNotMatchRegularExpression('/\son[a-z]+\s*=/i', $corpo, 'handler inline bloqueado pela CSP');
    }

    public function testFormularioDeSolicitacaoComErrosNaoUsaEstiloEmLinha(): void
    {
        $admin = $this->entrar('admin@exemplo.test');
        $resposta = $this->postarFormulario($admin, '/solicitacoes/nova', '/solicitacoes', [
            'empresa_id' => '',
            'titulo' => '',
            'prazo' => '',
            'descricao' => '',
        ]);

        self::assertSame(422, $resposta->status);
        self::assertDoesNotMatchRegularExpression('/\sstyle\s*=/i', $resposta->corpo, 'style= bloqueado pela CSP');
        self::assertDoesNotMatchRegularExpression('/\son[a-z]+\s*=/i', $resposta->corpo);
    }

    // ---- Apoio ----

    private function entrar(string $email): ClienteHttp
    {
        $cliente = $this->cliente();
        $token = ClienteHttp::extrairCsrf($cliente->get('/login')->corpo);
        self::assertNotNull($token);
        $resposta = $cliente->post('/login', ['_csrf' => $token, 'email' => $email, 'senha' => self::SENHA]);
        self::assertSame(302, $resposta->status);

        return $cliente;
    }

    private function token(ClienteHttp $cliente, string $caminho): string
    {
        $pagina = $cliente->get($caminho);
        self::assertSame(200, $pagina->status, $caminho);
        $token = ClienteHttp::extrairCsrf($pagina->corpo);
        self::assertNotNull($token);

        return $token;
    }

    /**
     * @param array<string, string> $dados
     */
    private function postarFormulario(ClienteHttp $cliente, string $formulario, string $destino, array $dados): RespostaHttp
    {
        $dados['_csrf'] = $this->token($cliente, $formulario);

        return $cliente->post($destino, $dados);
    }

    private function criarSolicitacao(ClienteHttp $admin, int $empresaId, string $titulo, ?string $prazo = null): int
    {
        $resposta = $this->postarFormulario($admin, '/solicitacoes/nova', '/solicitacoes', [
            'empresa_id' => (string) $empresaId,
            'titulo' => $titulo,
            'prazo' => $prazo ?? (new DateTimeImmutable('+10 days'))->format('Y-m-d'),
            'descricao' => 'Criada pelo teste E2E',
        ]);
        self::assertSame(302, $resposta->status);

        $consulta = $this->banco()->prepare('SELECT id FROM solicitacoes_documentos WHERE titulo = :titulo');
        $consulta->execute(['titulo' => $titulo]);
        $id = (int) $consulta->fetchColumn();
        self::assertGreaterThan(0, $id);
        $this->criadas[] = $id;

        return $id;
    }

    private function enviarArquivo(ClienteHttp $cliente, int $id, string $nome, string $conteudo, string $mime): RespostaHttp
    {
        $local = tempnam(sys_get_temp_dir(), 'tributo-e2e-');
        self::assertIsString($local);
        file_put_contents($local, $conteudo);
        $this->temporarios[] = $local;

        return $cliente->postarArquivo(
            '/portal/documentos/' . $id . '/enviar',
            ['_csrf' => $this->token($cliente, '/portal')],
            'arquivo',
            $local,
            $nome,
            $mime
        );
    }

    private function coluna(int $id, string $coluna): string
    {
        $valor = $this->colunaOuNulo($id, $coluna);
        self::assertNotNull($valor, $coluna);

        return $valor;
    }

    private function colunaOuNulo(int $id, string $coluna): ?string
    {
        // O nome da coluna vem do próprio teste (lista fixa), nunca de entrada externa.
        self::assertContains($coluna, ['status', 'motivo_recusa', 'arquivo_path', 'arquivo_nome_original']);
        $consulta = $this->banco()->prepare('SELECT ' . $coluna . ' FROM solicitacoes_documentos WHERE id = :id');
        $consulta->execute(['id' => $id]);
        $valor = $consulta->fetchColumn();

        return is_string($valor) ? $valor : null;
    }

    private function limparCriados(): void
    {
        $banco = $this->banco();
        foreach ($this->criadas as $id) {
            $consulta = $banco->prepare('SELECT arquivo_path FROM solicitacoes_documentos WHERE id = :id');
            $consulta->execute(['id' => $id]);
            $caminho = $consulta->fetchColumn();
            if (is_string($caminho) && $caminho !== '') {
                $arquivo = dirname(__DIR__, 2) . '/storage/uploads/' . basename($caminho);
                if (is_file($arquivo)) {
                    unlink($arquivo);
                }
            }
            $banco->prepare('DELETE FROM solicitacoes_documentos WHERE id = :id')->execute(['id' => $id]);
        }
        $this->criadas = [];

        foreach ($this->temporarios as $temporario) {
            if (is_file($temporario)) {
                unlink($temporario);
            }
        }
        $this->temporarios = [];
    }
}
