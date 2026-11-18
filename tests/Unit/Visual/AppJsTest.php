<?php

declare(strict_types=1);

namespace Tests\Unit\Visual;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * JavaScript global (app.js): o menu é do Bootstrap (offcanvas) e do botão "Recolher menu"; o bloco antigo que
 * alternava o menu por ID não tem mais consumidores. Os recursos que ainda dependem do JS não podem sumir.
 */
final class AppJsTest extends TestCase
{
    /**
     * @return array<string, array{string, string, string}>
     */
    public static function recursosPreservados(): array
    {
        // [trecho que o JS precisa conter, atributo/classe que a view emite, view]
        return [
            'filtros por card do portal' => ['data-filtro-portal', 'data-filtro-portal', 'portal/index.php'],
            'filtros do semáforo do painel' => ['data-filtro-semaforo', 'data-filtro-semaforo', 'gestao/painel/index.php'],
            'upload com nome do arquivo' => ['input-arquivo-oculto', 'input-arquivo-oculto', 'portal/index.php'],
            'confirmação por data-confirmar' => ['form[data-confirmar]', 'data-confirmar', 'documentos/solicitacoes/index.php'],
            'recolher o menu da ADR' => ['[data-barra-recolher]', 'data-barra-recolher', 'partials/estrutura-aplicacao.php'],
        ];
    }

    #[DataProvider('recursosPreservados')]
    public function testRecursoContinuaNoJsEQuemOUsaContinuaNaView(string $noJs, string $naView, string $view): void
    {
        $arquivoView = file_get_contents(dirname(__DIR__, 3) . '/app/Views/' . $view);
        self::assertIsString($arquivoView);

        self::assertStringContainsString($noJs, self::js(), 'o JS perdeu ' . $noJs);
        self::assertStringContainsString($naView, $arquivoView, $view . ' não emite ' . $naView);
    }

    public function testBlocoAntigoDeAlternanciaDoMenuNaoVoltou(): void
    {
        $js = self::js();

        foreach (['botao-toggle-menu', 'botao-fechar-menu-mobile', 'menu-backdrop', 'sidebar-colapsada', 'sidebar-aberta-mobile'] as $resto) {
            self::assertStringNotContainsString($resto, $js, $resto);
        }
    }

    private static function js(): string
    {
        $conteudo = file_get_contents(dirname(__DIR__, 3) . '/public/assets/js/app.js');
        self::assertIsString($conteudo);

        return $conteudo;
    }
}
