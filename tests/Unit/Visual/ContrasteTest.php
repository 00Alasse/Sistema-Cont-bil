<?php

declare(strict_types=1);

namespace Tests\Unit\Visual;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Contraste WCAG AA das cores da interface (ADR-024). Lê as variáveis --cor-* de app.css e
 * confere os pares usados em texto (4,5:1) e em componentes de interface, como bordas e foco (3:1).
 */
final class ContrasteTest extends TestCase
{
    /** @var array<string, string>|null */
    private static ?array $cores = null;

    /**
     * @return array<string, array{string, string, float}>
     */
    public static function pares(): array
    {
        $texto = 4.5;
        $componente = 3.0;

        return [
            'texto no fundo da página' => ['cor-texto', 'cor-fundo', $texto],
            'texto no cartão' => ['cor-texto', 'cor-superficie', $texto],
            'texto suave no cartão' => ['cor-texto-suave', 'cor-superficie', $texto],
            'texto suave no fundo da página' => ['cor-texto-suave', 'cor-fundo', $texto],
            'texto suave no cabeçalho de tabela' => ['cor-texto-suave', 'cor-superficie-alt', $texto],
            'link no cartão' => ['cor-link', 'cor-superficie', $texto],
            'link no fundo da página' => ['cor-link', 'cor-fundo', $texto],
            'marca como texto em fundo claro' => ['cor-marca', 'cor-superficie', $texto],
            'botão secundário em hover' => ['cor-marca-escura', 'cor-marca-suave', $texto],
            'menu: texto na barra lateral' => ['cor-barra-texto', 'cor-barra', $texto],
            'selo de sucesso' => ['cor-sucesso', 'cor-sucesso-fundo', $texto],
            'selo de aviso' => ['cor-aviso', 'cor-aviso-fundo', $texto],
            'selo de perigo' => ['cor-perigo', 'cor-perigo-fundo', $texto],
            'selo de informação' => ['cor-info', 'cor-info-fundo', $texto],
            'selo neutro' => ['cor-neutro', 'cor-neutro-fundo', $texto],
            'texto em alerta de sucesso' => ['cor-texto', 'cor-sucesso-fundo', $texto],
            'texto em alerta de erro' => ['cor-texto', 'cor-perigo-fundo', $texto],
            'texto em alerta de aviso' => ['cor-texto', 'cor-aviso-fundo', $texto],
            'texto em alerta de informação' => ['cor-texto', 'cor-info-fundo', $texto],
            'erro de campo' => ['cor-perigo', 'cor-superficie', $texto],
            'semáforo verde' => ['cor-sucesso', 'cor-superficie', $texto],
            'semáforo amarelo' => ['cor-aviso', 'cor-superficie', $texto],
            'semáforo vermelho' => ['cor-perigo', 'cor-superficie', $texto],
            'borda de campo' => ['cor-borda-forte', 'cor-superficie', $componente],
            'foco no conteúdo' => ['cor-foco', 'cor-superficie', $componente],
            'foco no fundo da página' => ['cor-foco', 'cor-fundo', $componente],
            'foco na barra lateral' => ['cor-foco-barra', 'cor-barra', $componente],
        ];
    }

    #[DataProvider('pares')]
    public function testParDeCoresAtendeOContrasteMinimo(string $primeiroPlano, string $fundo, float $minimo): void
    {
        $razao = self::razao(self::cor($primeiroPlano), self::cor($fundo));

        self::assertGreaterThanOrEqual(
            $minimo,
            $razao,
            sprintf('%s sobre %s tem contraste %.2f:1 (mínimo %.1f:1).', $primeiroPlano, $fundo, $razao, $minimo)
        );
    }

    public function testBotoesComTextoBrancoTemContraste(): void
    {
        foreach (['cor-marca', 'cor-marca-hover', 'cor-perigo', 'cor-barra'] as $fundo) {
            self::assertGreaterThanOrEqual(4.5, self::razao('#ffffff', self::cor($fundo)), $fundo);
        }
    }

    private static function cor(string $nome): string
    {
        if (self::$cores === null) {
            $css = file_get_contents(dirname(__DIR__, 3) . '/public/assets/css/app.css');
            self::assertIsString($css);
            preg_match_all('/--([a-z0-9-]+):\s*(#[0-9a-fA-F]{6})\s*;/', $css, $achados, PREG_SET_ORDER);
            self::$cores = [];
            foreach ($achados as $achado) {
                self::$cores[$achado[1]] = strtolower($achado[2]);
            }
        }

        self::assertArrayHasKey($nome, self::$cores, 'Variável --' . $nome . ' não encontrada em app.css.');

        return self::$cores[$nome];
    }

    private static function luminancia(string $hex): float
    {
        $canais = array_map(
            static function (string $parte): float {
                $c = hexdec($parte) / 255;

                return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
            },
            [substr($hex, 1, 2), substr($hex, 3, 2), substr($hex, 5, 2)]
        );

        return 0.2126 * $canais[0] + 0.7152 * $canais[1] + 0.0722 * $canais[2];
    }

    private static function razao(string $a, string $b): float
    {
        $clara = max(self::luminancia($a), self::luminancia($b));
        $escura = min(self::luminancia($a), self::luminancia($b));

        return ($clara + 0.05) / ($escura + 0.05);
    }
}