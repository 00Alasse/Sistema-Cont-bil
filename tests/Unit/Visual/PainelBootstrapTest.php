<?php

declare(strict_types=1);

namespace Tests\Unit\Visual;

use PHPUnit\Framework\TestCase;

/**
 * O painel usa os componentes do Bootstrap (ADR-024) e nenhuma classe sem definição no CSS. Leitura de
 * arquivos: não renderiza a página (a conferência visual em 360 a 1280 px é feita no navegador).
 */
final class PainelBootstrapTest extends TestCase
{
    private const RAIZ = __DIR__ . '/../../..';

    public function testNenhumaViewUsaClassesSemDefinicaoNoCss(): void
    {
        $css = (string) file_get_contents(self::RAIZ . '/public/assets/css/app.css');
        $iterador = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::RAIZ . '/app/Views', \FilesystemIterator::SKIP_DOTS));

        foreach ($iterador as $arquivo) {
            if (!$arquivo->isFile() || $arquivo->getExtension() !== 'php') {
                continue;
            }
            $html = (string) file_get_contents($arquivo->getPathname());
            foreach (['cartao', 'tabela-responsiva'] as $classe) {
                self::assertDoesNotMatchRegularExpression(
                    '/class="[^"]*(?<![\w-])' . $classe . '(?![\w-])[^"]*"/',
                    $html,
                    $arquivo->getFilename() . ' usa a classe .' . $classe . ', que não existe mais no CSS'
                );
            }
        }
        self::assertDoesNotMatchRegularExpression('/(?<![\w-])\.(cartao|tabela-responsiva)(?![\w-])/', $css);
    }

    public function testSecoesEDasTabelasDoPainelSaoComponentesBootstrap(): void
    {
        $view = (string) file_get_contents(self::RAIZ . '/app/Views/gestao/painel/index.php');

        foreach (['titulo-obrigacoes', 'titulo-regularidade', 'titulo-carga'] as $id) {
            self::assertStringContainsString('<section class="card card-body" aria-labelledby="' . $id . '">', $view);
        }
        self::assertSame(2, substr_count($view, '<div class="table-responsive">'));
        self::assertSame(2, preg_match_all('/<div class="table-responsive">\s*<table class="table align-middle"/', $view));
        self::assertStringContainsString('id="tabela-piores-indices"', $view);
        // Os rótulos de coluna do formato empilhado (removido) não têm mais função.
        self::assertStringNotContainsString('data-rotulo', $view);
    }
}
