<?php

declare(strict_types=1);

namespace Tests\E2E;

use Tests\Support\ClienteHttp;

/**
 * Identidade visual (ADR-024), pelo HTTP: nome oficial -Tributo, estilos e favicon nas telas,
 * nenhum estilo em linha (a CSP só aceita arquivos do próprio site), menu e ícones acessíveis
 * e arquivos de licença junto da fonte e dos ícones.
 */
final class InterfaceTest extends E2ETestCase
{
    private const SENHA = 'Senha@123';

    public function testNomeOficialAparecePadronizadoEmTodasAsTelas(): void
    {
        foreach ($this->telas() as $caminho => $resposta) {
            self::assertMatchesRegularExpression('#<title>[^<]* - -Tributo</title>#u', $resposta->corpo, $caminho);
            self::assertStringNotContainsString('-Tributo!', $resposta->corpo, $caminho);
            self::assertStringContainsString('<span class="marca-nome">-Tributo</span>', $resposta->corpo, $caminho);
        }
    }

    public function testTelasCarregamEstilosScriptEFavicon(): void
    {
        foreach ($this->telas() as $caminho => $resposta) {
            self::assertStringContainsString('href="/assets/vendor/bootstrap/bootstrap.min.css"', $resposta->corpo, $caminho);
            self::assertStringContainsString('href="/assets/css/app.css"', $resposta->corpo, $caminho);
            self::assertStringContainsString('<link rel="icon" type="image/svg+xml" href="/assets/img/favicon.svg">', $resposta->corpo, $caminho);
        }

        $raiz = dirname(__DIR__, 2) . '/public/assets';
        foreach (['css/app.css', 'js/app.js', 'img/favicon.svg', 'fonts/inter-latin-variable.woff2', 'vendor/bootstrap/bootstrap.min.css', 'vendor/bootstrap/bootstrap.bundle.min.js', 'vendor/bootstrap/LICENSE-Bootstrap.txt'] as $arquivo) {
            self::assertFileExists($raiz . '/' . $arquivo);
        }
        $css = (string) file_get_contents($raiz . '/css/app.css');
        preg_match_all('/url\("([^"]+)"\)/', $css, $referencias);
        foreach ($referencias[1] as $referencia) {
            // Imagem incorporada no próprio CSS (ex.: ícone SVG em mask-image) não é recurso externo nem arquivo:
            // só aceita data: de imagem, nunca de texto/HTML/script.
            if (str_starts_with($referencia, 'data:')) {
                self::assertMatchesRegularExpression('#^data:image/(?:svg\+xml|png|gif|webp)[;,]#', $referencia, 'Só data: de imagem é aceito no CSS.');
                continue;
            }
            self::assertFileExists(realpath($raiz . '/css') . '/' . $referencia, 'app.css referencia ' . $referencia);
            self::assertStringStartsNotWith('http', $referencia, 'Nada de recurso externo (CDN) no CSS.');
        }
        // Qualquer url() ou @import que aponte para fora do site (http, https ou //) continua proibido.
        self::assertDoesNotMatchRegularExpression('#url\(\s*["\']?(?:https?:)?//#i', $css, 'url() externa no CSS.');
        self::assertDoesNotMatchRegularExpression('#@import\s+(?:url\()?\s*["\']?(?:https?:)?//#i', $css, '@import externo no CSS.');
    }

    public function testNenhumaTelaUsaEstiloEmLinhaNemRecursoExterno(): void
    {
        foreach ($this->telas() as $caminho => $resposta) {
            self::assertDoesNotMatchRegularExpression('/\sstyle\s*=/i', $resposta->corpo, 'style="" em ' . $caminho);
            self::assertDoesNotMatchRegularExpression('/<style\b/i', $resposta->corpo, '<style> em ' . $caminho);
            // A CSP (script-src 'self') bloqueia handlers inline (onsubmit, onclick...): use data-confirmar e o app.js.
            self::assertDoesNotMatchRegularExpression('/\son[a-z]+\s*=/i', $resposta->corpo, 'handler on*= em ' . $caminho);
            self::assertDoesNotMatchRegularExpression('#(?:src|href)="https?://#i', $resposta->corpo, 'Recurso externo em ' . $caminho);
        }
    }

    public function testLayoutDoEscritorioTemPularConteudoMenuEBotaoAcessiveis(): void
    {
        $painel = $this->telas()['/painel'];

        self::assertStringContainsString('<a class="pular-conteudo" href="#conteudo-principal">Pular para o conteúdo</a>', $painel->corpo);
        self::assertStringContainsString('id="conteudo-principal"', $painel->corpo);
        self::assertStringContainsString('aria-label="Navegação principal"', $painel->corpo);
        // Menu lateral: offcanvas do Bootstrap no celular, com botão de abrir, de fechar e de recolher no desktop.
        self::assertStringContainsString('id="barra-lateral"', $painel->corpo);
        self::assertStringContainsString('data-bs-toggle="offcanvas" data-bs-target="#barra-lateral" aria-controls="barra-lateral"', $painel->corpo);
        self::assertStringContainsString('data-bs-dismiss="offcanvas"', $painel->corpo);
        self::assertStringContainsString('aria-label="Fechar menu"', $painel->corpo);
        self::assertStringContainsString('data-barra-recolher aria-expanded="true"', $painel->corpo);
        // O menu é feito de links comuns: funciona sem JavaScript.
        self::assertGreaterThanOrEqual(3, preg_match_all('#<a class="menu-item" href="/[^"]+" title="[^"]+">#', $painel->corpo));
        // O destaque da página atual é melhoria progressiva (app.js), nunca marcação do servidor.
        self::assertStringNotContainsString('aria-current="page"', preg_replace('#<nav class="alternador".*?</nav>#s', '', $painel->corpo) ?? '');
    }

    public function testIconesDecorativosSaoEscondidosDosLeitoresDeTela(): void
    {
        foreach ($this->telas() as $caminho => $resposta) {
            preg_match_all('#<svg class="icone[^>]*>#', $resposta->corpo, $icones);
            foreach ($icones[0] as $svg) {
                self::assertTrue(
                    str_contains($svg, 'aria-hidden="true"') || str_contains($svg, 'role="img"'),
                    'Ícone sem aria-hidden nem texto alternativo em ' . $caminho . ': ' . $svg
                );
            }
        }
    }

    public function testTelasAutenticadasCarregamOBootstrapJsDoOffcanvas(): void
    {
        foreach ($this->telasAutenticadas() as $caminho => $resposta) {
            self::assertStringContainsString(
                'src="/assets/vendor/bootstrap/bootstrap.bundle.min.js"',
                $resposta->corpo,
                'O offcanvas do menu depende do bundle do Bootstrap em ' . $caminho
            );
            self::assertStringContainsString('src="/assets/js/app.js"', $resposta->corpo, $caminho);
        }
    }

    public function testMarcaFicaNaBarraLateralEscuraELegivel(): void
    {
        foreach ($this->telasAutenticadas() as $caminho => $resposta) {
            // A marca padrão (variante escura) só é legível sobre a barra lateral escura; fora dela,
            // o texto branco some no cabeçalho claro.
            self::assertMatchesRegularExpression(
                '#<aside class="barra-lateral[^"]*".*?<a class="marca" href="[^"]+"><svg class="marca-simbolo".*?<span class="marca-nome">-Tributo</span></a>#s',
                $resposta->corpo,
                'Marca -Tributo ausente da barra lateral em ' . $caminho
            );
            self::assertDoesNotMatchRegularExpression(
                '#<header class="cabecalho">(?:(?!</header>).)*class="marca[ "]#s',
                $resposta->corpo,
                'Marca de texto branco dentro do cabeçalho claro em ' . $caminho
            );
        }
    }

    public function testPortalEEscritorioUsamAMesmaEstruturaDeMenu(): void
    {
        $telas = $this->telasAutenticadas();

        foreach (['/portal', '/avaliacao', '/painel', '/solicitacoes'] as $caminho) {
            $corpo = $telas[$caminho]->corpo;
            self::assertStringContainsString('id="barra-lateral"', $corpo, $caminho);
            self::assertStringContainsString('data-bs-toggle="offcanvas" data-bs-target="#barra-lateral" aria-controls="barra-lateral"', $corpo, $caminho);
            self::assertStringContainsString('data-barra-recolher aria-expanded="true"', $corpo, $caminho);
            self::assertStringContainsString('aria-label="Navegação principal"', $corpo, $caminho);
            self::assertGreaterThanOrEqual(1, preg_match_all('#<a class="menu-item" href="/[^"]+" title="[^"]+">#', $corpo), $caminho);
            // O formulário de saída continua sendo POST com token CSRF.
            self::assertMatchesRegularExpression('#<form method="post" action="/logout">\s*<input type="hidden" name="_csrf"#', $corpo, $caminho);
        }
    }

    public function testNenhumaTelaTemRestosDoLayoutAntigo(): void
    {
        $restos = ['botao-menu-toggle', 'sidebar-principal', 'cabecalho-marca-area', 'menu-backdrop', 'badge-perfil', 'sidebar-rodape-usuario'];

        foreach ($this->telasAutenticadas() as $caminho => $resposta) {
            foreach ($restos as $resto) {
                self::assertStringNotContainsString($resto, $resposta->corpo, $resto . ' em ' . $caminho);
            }
        }
    }

    public function testCspNaoFoiFlexibilizadaParaEsconderEstilosEmLinha(): void
    {
        foreach ($this->telas() as $caminho => $resposta) {
            $csp = $resposta->header('Content-Security-Policy') ?? '';

            self::assertStringContainsString("style-src 'self'", $csp, $caminho);
            self::assertStringContainsString("script-src 'self'", $csp, $caminho);
            self::assertStringNotContainsString('unsafe-inline', $csp, $caminho);
            self::assertStringNotContainsString('unsafe-eval', $csp, $caminho);
        }
    }

    public function testPaginaInicialApresentaOProdutoELevaAoLoginSemRedirecionamentoAutomatico(): void
    {
        $resposta = $this->cliente()->get('/');

        self::assertSame(200, $resposta->status);
        // Nada de meta refresh: a pessoa escolhe entrar pelo botão.
        self::assertStringNotContainsString('http-equiv="refresh"', $resposta->corpo);
        self::assertMatchesRegularExpression('#<a class="btn btn-primary" href="/login">Entrar</a>#', $resposta->corpo);
        self::assertStringContainsString('<span class="marca-nome">-Tributo</span>', $resposta->corpo);
        self::assertStringContainsString('<title>Início - -Tributo</title>', $resposta->corpo);
        self::assertStringContainsString('href="/assets/vendor/bootstrap/bootstrap.min.css"', $resposta->corpo);
        self::assertStringContainsString('<link rel="icon" type="image/svg+xml" href="/assets/img/favicon.svg">', $resposta->corpo);
        self::assertStringContainsString('Não atesta regularidade fiscal', $resposta->corpo);
    }

    public function testPaginaInicialParaQuemJaEstaAutenticadoMantemOCaminhoParaOPainel(): void
    {
        $admin = $this->entrar('admin@exemplo.test');
        $cliente = $this->entrar('cliente.padaria@exemplo.test');

        self::assertSame(200, $admin->get('/')->status);
        self::assertSame(200, $cliente->get('/')->status);
        // O botão "Entrar" leva a /login, e quem já tem sessão segue direto para a própria área.
        self::assertSame('/painel', $admin->get('/login')->location());
        self::assertSame('/portal', $cliente->get('/login')->location());
    }

    public function testFonteEIconesTemLicencaRegistrada(): void
    {
        $raiz = dirname(__DIR__, 2);

        self::assertStringContainsString('SIL OPEN FONT LICENSE Version 1.1', (string) file_get_contents($raiz . '/public/assets/fonts/LICENSE-Inter.txt'));
        $creditos = (string) file_get_contents($raiz . '/docs/LICENCAS-TERCEIROS.md');
        self::assertStringContainsString('ISC License', $creditos);
        self::assertStringContainsString('The MIT License (MIT)', $creditos);
        self::assertStringContainsString('Inter', $creditos);
        self::assertStringContainsString('Lucide', $creditos);
        self::assertStringContainsString('Bootstrap', $creditos);
        self::assertStringContainsString('Licensed under MIT', (string) file_get_contents($raiz . '/public/assets/vendor/bootstrap/bootstrap.min.css'));
    }

    /**
     * Telas de cada layout, buscadas uma vez por teste.
     *
     * @return array<string, \Tests\Support\RespostaHttp>
     */
    private function telas(): array
    {
        $visitante = $this->cliente();
        $admin = $this->entrar('admin@exemplo.test');
        $cliente = $this->entrar('cliente.padaria@exemplo.test');

        return [
            '/' => $visitante->get('/'),
            '/login' => $visitante->get('/login'),
            '/caminho-inexistente' => $visitante->get('/caminho-inexistente'),
            '/painel' => $admin->get('/painel'),
            '/empresas' => $admin->get('/empresas'),
            '/empresas/nova' => $admin->get('/empresas/nova'),
            '/obrigacoes' => $admin->get('/obrigacoes'),
            '/tipos-obrigacao' => $admin->get('/tipos-obrigacao'),
            '/solicitacoes' => $admin->get('/solicitacoes'),
            '/avaliacoes' => $admin->get('/avaliacoes'),
            '/usuarios' => $admin->get('/usuarios'),
            '/avaliacao (escritório)' => $admin->get('/avaliacao'),
            '/portal' => $cliente->get('/portal'),
            '/avaliacao' => $cliente->get('/avaliacao'),
            '/403' => $cliente->get('/painel'),
        ];
    }

    /**
     * Só as telas com a barra lateral (escritório e portal): sem página inicial, login nem erros.
     *
     * @return array<string, \Tests\Support\RespostaHttp>
     */
    private function telasAutenticadas(): array
    {
        return array_diff_key(
            $this->telas(),
            array_flip(['/', '/login', '/caminho-inexistente', '/403'])
        );
    }

    private function entrar(string $email): ClienteHttp
    {
        $cliente = $this->cliente();
        $token = ClienteHttp::extrairCsrf($cliente->get('/login')->corpo);
        self::assertNotNull($token);
        $resposta = $cliente->post('/login', ['_csrf' => $token, 'email' => $email, 'senha' => self::SENHA]);
        self::assertSame(302, $resposta->status);

        return $cliente;
    }
}