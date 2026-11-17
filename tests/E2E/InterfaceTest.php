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
            self::assertTrue(
                str_contains($resposta->corpo, '<span class="marca-nome">-Tributo</span>')
                || str_contains($resposta->corpo, '<span class="marca-hifen">-</span>Tributo</a>'),
                'Marca -Tributo ausente em ' . $caminho
            );
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
        foreach (['css/app.css', 'js/app.js', 'img/favicon.svg', 'fonts/inter-latin-variable.woff2', 'vendor/bootstrap/bootstrap.min.css', 'vendor/bootstrap/LICENSE-Bootstrap.txt'] as $arquivo) {
            self::assertFileExists($raiz . '/' . $arquivo);
        }
        $css = (string) file_get_contents($raiz . '/css/app.css');
        preg_match_all('/url\("([^"]+)"\)/', $css, $referencias);
        foreach ($referencias[1] as $referencia) {
            self::assertStringStartsNotWith('http', $referencia, 'Nada de recurso externo (CDN) no CSS.');
            if (!str_starts_with($referencia, 'data:')) {
                self::assertFileExists(realpath($raiz . '/css') . '/' . $referencia, 'app.css referencia ' . $referencia);
            }
        }
    }

    public function testNenhumaTelaUsaEstiloEmLinhaNemRecursoExterno(): void
    {
        foreach ($this->telas() as $caminho => $resposta) {
            self::assertDoesNotMatchRegularExpression('/\sstyle\s*=/i', $resposta->corpo, 'style="" em ' . $caminho);
            self::assertDoesNotMatchRegularExpression('/<style\b/i', $resposta->corpo, '<style> em ' . $caminho);
            self::assertDoesNotMatchRegularExpression('#(?:src|href)="https?://#i', $resposta->corpo, 'Recurso externo em ' . $caminho);
        }
    }

    public function testLayoutDoEscritorioTemPularConteudoMenuEBotaoAcessiveis(): void
    {
        $painel = $this->telas()['/painel'];

        self::assertStringContainsString('<a class="pular-conteudo" href="#conteudo-principal">Pular para o conteúdo</a>', $painel->corpo);
        self::assertStringContainsString('id="conteudo-principal"', $painel->corpo);
        self::assertStringContainsString('aria-label="Navegação principal"', $painel->corpo);
        // Menu lateral: botão de minimizar/maximizar, gaveta no celular (fechar e fundo) e links comuns.
        self::assertStringContainsString('id="botao-toggle-menu"', $painel->corpo);
        self::assertStringContainsString('id="sidebar-principal"', $painel->corpo);
        self::assertStringContainsString('id="botao-fechar-menu-mobile"', $painel->corpo);
        self::assertStringContainsString('id="menu-backdrop"', $painel->corpo);
        self::assertGreaterThanOrEqual(3, preg_match_all('#<li>\s*<a href="/[^"]+" data-icone="[^"]+"#', $painel->corpo));
        // O item da página atual é marcado no servidor, uma única vez.
        self::assertSame(1, preg_match_all('#<a href="/painel"[^>]*aria-current="page"#', $painel->corpo));
    }

    public function testTelasDosModulosRespondemSemErroInterno(): void
    {
        $esperados = ['/caminho-inexistente' => 404, '/403' => 403];
        foreach ($this->telas() as $caminho => $resposta) {
            self::assertSame($esperados[$caminho] ?? 200, $resposta->status, 'Status inesperado em ' . $caminho);
        }
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
            '/solicitacoes/nova' => $admin->get('/solicitacoes/nova'),
            '/avaliacoes' => $admin->get('/avaliacoes'),
            '/avaliacao' => $admin->get('/avaliacao'),
            '/403' => $cliente->get('/painel'),
            '/portal' => $cliente->get('/portal'),
        ];
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