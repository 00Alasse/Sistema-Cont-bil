<?php

declare(strict_types=1);

namespace Tests\Unit\Visual;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3) . '/app/Core/helpers.php';

/**
 * Ícones do menu (ADR-024): cada item configurado em config/modules/*.php precisa de um desenho próprio.
 * Um nome fora da lista de partials/navegacao.php (ou sem desenho em partials/icone.php) cai em "padrao",
 * um círculo genérico: foi o que aconteceu com Documentos e Avaliações.
 */
final class IconesMenuTest extends TestCase
{
    private const CIRCULO_GENERICO = '<circle cx="12" cy="12" r="10" />';

    /** Desenhos Lucide (file-text e star), idênticos aos do projeto lucide-icons/lucide. */
    private const FILE_TEXT = 'M6 22a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h8a2.4 2.4 0 0 1 1.704.706l3.588 3.588A2.4 2.4 0 0 1 20 8v12a2 2 0 0 1-2 2z';
    private const STAR = 'M11.525 2.295a.53.53 0 0 1 .95 0l2.31 4.679a2.123 2.123 0 0 0 1.595 1.16l5.166.756a.53.53 0 0 1 .294.904l-3.736 3.638a2.123 2.123 0 0 0-.611 1.878l.882 5.14a.53.53 0 0 1-.771.56l-4.618-2.428a2.122 2.122 0 0 0-1.973 0L6.396 21.01a.53.53 0 0 1-.77-.56l.881-5.139a2.122 2.122 0 0 0-.611-1.879L2.16 9.795a.53.53 0 0 1 .294-.906l5.165-.755a2.122 2.122 0 0 0 1.597-1.16z';

    /**
     * @return array<string, array{string}>
     */
    public static function iconesConfiguradosNosModulos(): array
    {
        $nomes = [];
        foreach (glob(dirname(__DIR__, 3) . '/config/modules/*.php') ?: [] as $arquivo) {
            $texto = (string) file_get_contents($arquivo);
            preg_match_all("/'icone'\s*=>\s*'([a-z_]+)'/", $texto, $achados);
            foreach ($achados[1] as $nome) {
                $nomes[$nome] = [$nome];
            }
        }
        self::assertNotEmpty($nomes, 'nenhum ícone de menu encontrado em config/modules');

        return $nomes;
    }

    #[DataProvider('iconesConfiguradosNosModulos')]
    public function testIconeDeMenuConfiguradoTemDesenhoProprioEPassaPelaNavegacao(string $nome): void
    {
        $svg = $this->icone($nome);
        $generico = $this->icone('nome-que-nao-existe');

        self::assertNotSame($generico, $svg, 'o ícone "' . $nome . '" caiu no círculo genérico de icone.php');
        self::assertStringNotContainsString(self::CIRCULO_GENERICO . '</svg>', str_replace("\n", '', $svg), $nome);

        // E a navegação precisa liberar o nome: antes, só 6 nomes passavam e os demais viravam "padrao".
        $menu = $this->navegacao([['rotulo' => 'Item', 'url' => '/x', 'icone' => $nome, 'ordem' => 1]]);
        self::assertStringContainsString($this->desenhoDe($svg), $menu, 'navegacao.php não libera o ícone "' . $nome . '"');
    }

    public function testDocumentosUsaFileTextEAvaliacaoUsaStar(): void
    {
        $menu = $this->navegacao([
            ['rotulo' => 'Documentos', 'url' => '/solicitacoes', 'icone' => 'documentos', 'ordem' => 25],
            ['rotulo' => 'Avaliações', 'url' => '/avaliacoes', 'icone' => 'avaliacao', 'ordem' => 90],
        ]);

        preg_match_all('#<a class="menu-item"[^>]*>.*?</a>#s', $menu, $itens);
        self::assertCount(2, $itens[0]);
        self::assertStringContainsString(self::FILE_TEXT, $itens[0][0]);
        self::assertStringContainsString('M14 2v5a1 1 0 0 0 1 1h5', $itens[0][0]);
        self::assertStringContainsString(self::STAR, $itens[0][1]);
        foreach ($itens[0] as $item) {
            self::assertStringNotContainsString(self::CIRCULO_GENERICO, $item, 'ícone genérico no menu');
        }
    }

    public function testIconesDecorativosDoMenuContinuamEscondidosDosLeitoresDeTela(): void
    {
        $menu = $this->navegacao([
            ['rotulo' => 'Documentos', 'url' => '/solicitacoes', 'icone' => 'documentos', 'ordem' => 25],
            ['rotulo' => 'Avaliações', 'url' => '/avaliacoes', 'icone' => 'avaliacao', 'ordem' => 90],
        ]);

        self::assertSame(2, substr_count($menu, '<span class="menu-icone" aria-hidden="true">'));
        // O tamanho e o traço são os mesmos dos demais ícones (mesmo partial).
        self::assertSame(2, substr_count($menu, 'width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"'));
        self::assertSame(2, substr_count($menu, '<span class="menu-rotulo">'));
    }

    public function testDemaisIconesDoMenuNaoMudaram(): void
    {
        $trechos = [
            'painel' => 'rect width="7" height="9" x="3" y="3"',
            'empresa' => 'M12 10h.01',
            'obrigacoes' => 'm9 15 2 2 4-4',
            'tipos' => 'M13 5h8',
            'usuarios' => 'M16 3.128a4 4 0 0 1 0 7.744',
            'portal' => 'M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8',
        ];

        foreach ($trechos as $nome => $trecho) {
            $menu = $this->navegacao([['rotulo' => 'X', 'url' => '/x', 'icone' => $nome, 'ordem' => 1]]);
            self::assertStringContainsString($trecho, $menu, $nome);
        }
    }

    private function icone(string $nome): string
    {
        $icone = $nome;
        ob_start();
        require dirname(__DIR__, 3) . '/app/Views/partials/icone.php';

        return (string) ob_get_clean();
    }

    /**
     * @param list<array{rotulo: string, url: string, icone: string, ordem: int}> $itens
     */
    private function navegacao(array $itens): string
    {
        $itensMenu = $itens;
        ob_start();
        require dirname(__DIR__, 3) . '/app/Views/partials/navegacao.php';

        return (string) ob_get_clean();
    }

    /**
     * Só os traços do SVG (sem o <svg> de fora), para comparar com o que a navegação imprime.
     */
    private function desenhoDe(string $svg): string
    {
        preg_match('#<svg[^>]*>(.*)</svg>#s', $svg, $partes);

        return trim($partes[1] ?? '');
    }
}
