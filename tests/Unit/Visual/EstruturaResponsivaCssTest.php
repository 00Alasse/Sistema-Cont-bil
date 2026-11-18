<?php

declare(strict_types=1);

namespace Tests\Unit\Visual;

use PHPUnit\Framework\TestCase;

/**
 * Estrutura e menu da ADR-024, lendo o app.css: abaixo de 992 px o menu é um offcanvas FORA do fluxo (o
 * conteúdo ocupa a largura toda), e o CSS do menu em trilho/painel claro da main não pode voltar a vazar
 * para o menu azul-escuro da ADR. (A medição real em 360, 768, 991 e 992 px é feita no navegador; aqui só
 * se protege o CSS.)
 */
final class EstruturaResponsivaCssTest extends TestCase
{
    private static ?string $css = null;

    public function testGridDaEstruturaSoValeAPartirDe992Pixels(): void
    {
        $css = self::css();

        // Fora de qualquer @media (coluna 0), ".estrutura-aplicacao" nunca define grid nem alinhamento de itens.
        preg_match_all('/^\.estrutura-aplicacao\s*\{([^}]*)\}/m', $css, $regrasGlobais);
        foreach ($regrasGlobais[1] as $corpo) {
            self::assertStringNotContainsString('display: grid', $corpo, 'grid global na estrutura');
            self::assertStringNotContainsString('grid-template-columns', $corpo, 'colunas globais na estrutura');
            // align-items: start deixava a barra lateral com a altura da janela: o menu sumia ao rolar.
            self::assertStringNotContainsString('align-items', $corpo, 'a barra lateral precisa esticar até o fim da página');
        }

        // E o grid existe no @media (min-width: 992px).
        self::assertMatchesRegularExpression(
            '/@media \(min-width: 992px\)\s*\{\s*\.estrutura-aplicacao\s*\{[^}]*display: grid/s',
            $css
        );
    }

    public function testNaoHaRegrasDeTrilhoMobileParaOMenu(): void
    {
        $bloco = $this->blocoDaMedia('@media (max-width: 48rem)');

        // O trilho de ícones era do menu antigo; o menu da ADR fica no offcanvas e precisa dos rótulos.
        self::assertStringNotContainsString('.navegacao-principal', $bloco);
        self::assertDoesNotMatchRegularExpression('/\.estrutura-aplicacao\s*\{[^}]*grid-template-columns/', $bloco);
    }

    public function testMenuAntigoNaoVazaParaOMenuDaAdr(): void
    {
        $css = self::css();

        // O painel claro (#f1f5f9, sticky) e o link com cor própria venciam o .menu-item azul por especificidade.
        self::assertDoesNotMatchRegularExpression('/^\.navegacao-principal\s*\{/m', $css);
        self::assertDoesNotMatchRegularExpression('/\.navegacao-principal\s+a[\s:{,\[.]/', $css);
        // O ícone da ADR é um SVG: a caixa com color:transparent e o ::before em máscara o escondiam.
        self::assertDoesNotMatchRegularExpression('/\.menu-icone[^{]*\{[^}]*color:\s*transparent/', $css);
        self::assertDoesNotMatchRegularExpression('/\.menu-icone::before/', $css);
        self::assertStringNotContainsString('data-icone', $css);
    }

    public function testResiduosDoLayoutAntigoNaoVoltam(): void
    {
        $css = self::css();

        foreach (['sidebar-colapsada', 'sidebar-aberta-mobile', 'sidebar-rodape-usuario', 'botao-menu-toggle', 'menu-backdrop', 'cabecalho-marca-area', 'badge-perfil', 'botao-sair'] as $resto) {
            self::assertStringNotContainsString($resto, $css, $resto);
        }
    }

    public function testCabecalhoNaoForcaTextoBrancoSobreFundoClaro(): void
    {
        // O .cabecalho da ADR é claro: um color:#fff herdado deixava o nome do usuário invisível.
        preg_match_all('/^\.cabecalho\s*\{([^}]*)\}/m', self::css(), $regras);
        self::assertNotEmpty($regras[1]);
        foreach ($regras[1] as $corpo) {
            self::assertDoesNotMatchRegularExpression('/(?<![-\w])color:\s*#fff/i', $corpo);
        }
    }

    public function testPainelDoOffcanvasNaoDesenhaContornoDeFoco(): void
    {
        // O Bootstrap foca o próprio painel ao abrir; sem esta regra o :focus-visible global pintava uma linha azul.
        self::assertMatchesRegularExpression(
            '/\.barra-lateral:focus-visible\s*\{[^}]*outline:\s*0/s',
            self::css()
        );
        // Os controles de dentro continuam com contorno de foco.
        self::assertMatchesRegularExpression('/\.barra-lateral :focus-visible\s*\{[^}]*outline-color/s', self::css());
    }

    private static function css(): string
    {
        if (self::$css === null) {
            $conteudo = file_get_contents(dirname(__DIR__, 3) . '/public/assets/css/app.css');
            self::assertIsString($conteudo);
            self::$css = $conteudo;
        }

        return self::$css;
    }

    /**
     * Texto do bloco @media (entre as chaves externas), por contagem de chaves.
     */
    private function blocoDaMedia(string $consulta): string
    {
        $css = self::css();
        $inicio = strpos($css, $consulta);
        self::assertNotFalse($inicio, $consulta . ' não encontrado');
        $abre = strpos($css, '{', $inicio);
        self::assertNotFalse($abre);

        $nivel = 0;
        $tamanho = strlen($css);
        for ($i = $abre; $i < $tamanho; $i++) {
            if ($css[$i] === '{') {
                $nivel++;
            } elseif ($css[$i] === '}') {
                $nivel--;
                if ($nivel === 0) {
                    return substr($css, $abre + 1, $i - $abre - 1);
                }
            }
        }
        self::fail('Bloco ' . $consulta . ' sem fechamento');
    }
}
