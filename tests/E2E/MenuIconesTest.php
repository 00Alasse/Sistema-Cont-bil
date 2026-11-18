<?php

declare(strict_types=1);

namespace Tests\E2E;

use Tests\Support\ClienteHttp;

/**
 * Ícones do menu lateral nas páginas reais: Documentos usa file-text e Avaliações/Avaliar sistema usam star
 * (Lucide), e nenhum item do menu volta ao círculo genérico.
 */
final class MenuIconesTest extends E2ETestCase
{
    private const SENHA = 'Senha@123';
    private const FILE_TEXT = 'M14 2v5a1 1 0 0 0 1 1h5';
    private const STAR = 'M11.525 2.295a.53.53 0 0 1 .95 0l2.31 4.679';
    private const CIRCULO_GENERICO = '<circle cx="12" cy="12" r="10" />';

    public function testMenuDoEscritorioMostraFileTextEmDocumentosEStarEmAvaliacoes(): void
    {
        $itens = $this->itensDoMenu($this->entrar('admin@exemplo.test')->get('/painel')->corpo);

        self::assertSame(
            ['Painel', 'Empresas', 'Documentos', 'Obrigações', 'Tipos de obrigação', 'Usuários', 'Avaliações'],
            array_keys($itens)
        );
        self::assertStringContainsString(self::FILE_TEXT, $itens['Documentos']);
        self::assertStringContainsString(self::STAR, $itens['Avaliações']);
        $this->semIconeGenerico($itens);
    }

    public function testMenuDoPortalMostraStarEmAvaliarSistema(): void
    {
        $itens = $this->itensDoMenu($this->entrar('cliente.padaria@exemplo.test')->get('/portal')->corpo);

        self::assertSame(['Portal do cliente', 'Avaliar sistema'], array_keys($itens));
        self::assertStringContainsString(self::STAR, $itens['Avaliar sistema']);
        self::assertStringContainsString('M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8', $itens['Portal do cliente']);
        $this->semIconeGenerico($itens);
    }

    public function testOsIconesDeDocumentosEAvaliacoesContinuamDecorativos(): void
    {
        $corpo = $this->entrar('admin@exemplo.test')->get('/painel')->corpo;

        foreach ($this->itensDoMenu($corpo) as $rotulo => $html) {
            self::assertStringContainsString('<span class="menu-icone" aria-hidden="true">', $html, $rotulo);
            self::assertStringContainsString('<span class="menu-rotulo">' . $rotulo . '</span>', $html, $rotulo);
        }
    }

    /**
     * @param array<string, string> $itens
     */
    private function semIconeGenerico(array $itens): void
    {
        foreach ($itens as $rotulo => $html) {
            self::assertStringNotContainsString(self::CIRCULO_GENERICO, $html, 'ícone genérico em ' . $rotulo);
        }
    }

    /**
     * @return array<string, string> HTML de cada item do menu, indexado pelo rótulo
     */
    private function itensDoMenu(string $html): array
    {
        preg_match_all('#<a class="menu-item"[^>]*>.*?<span class="menu-rotulo">([^<]+)</span>\s*</a>#s', $html, $achados, PREG_SET_ORDER);
        $itens = [];
        foreach ($achados as $achado) {
            $itens[html_entity_decode($achado[1])] = $achado[0];
        }
        self::assertNotEmpty($itens);

        return $itens;
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
