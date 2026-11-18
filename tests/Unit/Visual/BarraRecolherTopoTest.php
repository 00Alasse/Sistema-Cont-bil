<?php

declare(strict_types=1);

namespace Tests\Unit\Visual;

use PHPUnit\Framework\TestCase;

/**
 * O botão de recolher/expandir o menu fica no topo fixo da barra lateral (ao lado da marca), fora da área
 * rolável dos itens, e continua sendo um único botão. Leitura de arquivos: não renderiza nem executa o JS.
 */
final class BarraRecolherTopoTest extends TestCase
{
    private const RAIZ = __DIR__ . '/../../..';

    public function testHaUmUnicoBotaoENoCabecalhoFixoDaBarra(): void
    {
        $html = (string) file_get_contents(self::RAIZ . '/app/Views/partials/estrutura-aplicacao.php');

        self::assertSame(1, substr_count($html, 'data-barra-recolher'));

        $topoInicio = strpos($html, '<div class="barra-topo">');
        $menuInicio = strpos($html, '<div class="barra-menu">');
        $botao = strpos($html, 'data-barra-recolher');
        self::assertNotFalse($topoInicio);
        self::assertNotFalse($menuInicio);
        self::assertNotFalse($botao);
        // Dentro de .barra-topo (antes de .barra-menu, a área rolável) e depois da marca.
        self::assertGreaterThan($topoInicio, $botao);
        self::assertLessThan($menuInicio, $botao);
        self::assertGreaterThan(strpos($html, "/marca.php'"), $botao);
        // O rodapé que o abrigava não existe mais.
        self::assertStringNotContainsString('barra-rodape', $html);
        // O texto do botão continua disponível a leitores de tela (o JS o atualiza) e ele mantém aria-expanded.
        self::assertStringContainsString('barra-recolher-rotulo visually-hidden', $html);
        self::assertStringContainsString('data-barra-recolher aria-expanded="true"', $html);
    }

    public function testApenasAreaDeItensRolaETopoNaoRola(): void
    {
        $css = (string) file_get_contents(self::RAIZ . '/public/assets/css/app.css');

        self::assertMatchesRegularExpression('/\.barra-menu\s*\{[^}]*overflow-y:\s*auto/', $css);
        self::assertDoesNotMatchRegularExpression('/\.barra-topo\s*\{[^}]*overflow/', $css);
        self::assertDoesNotMatchRegularExpression('/\.barra-rodape/', $css);
    }

    public function testNoDesktopABarraFicaFixaNaJanelaERolaSoOMenu(): void
    {
        $css = (string) file_get_contents(self::RAIZ . '/public/assets/css/app.css');

        // Pega o bloco @media (min-width: 992px) da estrutura (o que contém .barra-lateral fixa).
        self::assertSame(1, preg_match('/@media \(min-width: 992px\)\s*\{\s*\.estrutura-aplicacao\s*\{.*?\n\}\n/s', $css, $m));
        $desktop = $m[0];

        self::assertMatchesRegularExpression('/\.barra-lateral\s*\{[^}]*position:\s*fixed/', $desktop);
        self::assertMatchesRegularExpression('/\.barra-lateral\s*\{[^}]*top:\s*0;[^}]*bottom:\s*0/', $desktop);
        self::assertMatchesRegularExpression('/\.barra-lateral\s*\{[^}]*height:\s*100vh;\s*height:\s*100dvh/', $desktop);
        self::assertMatchesRegularExpression('/\.barra-lateral\s*\{[^}]*width:\s*var\(--largura-barra\)/', $desktop);
        // Recolhida: só a largura muda; a altura continua a da janela.
        self::assertMatchesRegularExpression('/\.barra-recolhida \.barra-lateral\s*\{\s*width:\s*var\(--largura-barra-recolhida\)/', $desktop);
        // Fora do fluxo: o conteúdo continua na 2ª coluna do grid, que reserva a largura da barra.
        self::assertMatchesRegularExpression('/\.area-principal\s*\{\s*grid-column:\s*2/', $desktop);
        self::assertMatchesRegularExpression('/grid-template-columns:\s*var\(--largura-barra\) minmax\(0, 1fr\)/', $desktop);
        // Topo não encolhe e a área dos atalhos pode encolher (min-height: 0) para rolar sozinha.
        self::assertMatchesRegularExpression('/\.barra-topo\s*\{\s*flex:\s*0 0 auto/', $desktop);
        self::assertMatchesRegularExpression('/\.barra-menu\s*\{\s*min-height:\s*0/', $desktop);
        // Sem alturas em px/rem fixas e sem o sticky antigo.
        self::assertStringNotContainsString('position: sticky', $desktop);
    }

    public function testNoMobileABarraContinuaOffcanvasForaDaTelaQuandoFechada(): void
    {
        $css = (string) file_get_contents(self::RAIZ . '/public/assets/css/app.css');

        // "position: fixed" da barra só existe dentro do @media desktop; fora dele ela segue o offcanvas do Bootstrap.
        $foraDoDesktop = preg_replace('/@media \(min-width: 992px\)\s*\{\s*\.estrutura-aplicacao\s*\{.*?\n\}\n/s', '', $css) ?? '';
        self::assertDoesNotMatchRegularExpression('/\.barra-lateral\s*\{[^}]*position:\s*fixed/', $foraDoDesktop);
        // O "menu visível sem JS" vale só abaixo de 992 px, para não desfazer a barra fixa no desktop.
        self::assertMatchesRegularExpression('/@media \(max-width: 991\.98px\)\s*\{\s*html:not\(\.js\) \.barra-lateral\s*\{[^}]*position:\s*static/', $css);
    }

    public function testBotaoSoApareceComJsEAPartirDe992PxEEmpilhaComAMarcaQuandoRecolhido(): void
    {
        $css = (string) file_get_contents(self::RAIZ . '/public/assets/css/app.css');

        // Escondido por padrão (offcanvas/mobile e sem JS); liberado só dentro do @media desktop com JS.
        self::assertMatchesRegularExpression('/\.barra-recolher\s*\{\s*display:\s*none;/', $css);
        self::assertMatchesRegularExpression('/@media \(min-width: 992px\)\s*\{.*?\.js \.barra-recolher\s*\{\s*display:\s*flex/s', $css);
        // Recolhida (4,5 rem): marca e botão em coluna, sem se sobrepor.
        self::assertMatchesRegularExpression('/\.barra-recolhida \.barra-topo\s*\{[^}]*flex-direction:\s*column/', $css);
    }
}
