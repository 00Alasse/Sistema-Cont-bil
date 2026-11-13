<?php

declare(strict_types=1);

namespace Tests\Unit\Fiscal;

use App\Services\Fiscal\CalculadoraVencimento;
use App\Support\Competencia;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * ARCHITECTURE §10.2: vencimento = mês(competência + meses_apos_competencia),
 * no dia min(dia_vencimento, último dia desse mês). Sem ajuste de feriados ou fins de semana.
 */
final class CalculadoraVencimentoTest extends TestCase
{
    #[DataProvider('casos')]
    public function testCalculaVencimento(string $competencia, int $dia, int $meses, string $esperado): void
    {
        $vencimento = (new CalculadoraVencimento())->calcular(Competencia::deTexto($competencia), $dia, $meses);

        self::assertSame($esperado, $vencimento->format('Y-m-d'));
    }

    /**
     * @return array<string, array{string, int, int, string}>
     */
    public static function casos(): array
    {
        return [
            'mês seguinte' => ['2026-03', 20, 1, '2026-04-20'],
            'dia 31 em fevereiro comum' => ['2026-01', 31, 1, '2026-02-28'],
            'dia 31 em fevereiro bissexto' => ['2028-01', 31, 1, '2028-02-29'],
            'dia 30 em fevereiro bissexto' => ['2028-01', 30, 1, '2028-02-29'],
            'dia 31 em mês de 30 dias' => ['2026-03', 31, 1, '2026-04-30'],
            'dia 31 em mês de 31 dias' => ['2026-06', 31, 1, '2026-07-31'],
            'zero meses' => ['2026-05', 15, 0, '2026-05-15'],
            'zero meses no dia 1' => ['2026-05', 1, 0, '2026-05-01'],
            'doze meses' => ['2026-05', 10, 12, '2027-05-10'],
            'virada de ano' => ['2026-12', 20, 1, '2027-01-20'],
            'virada de ano para fevereiro' => ['2026-12', 31, 2, '2027-02-28'],
        ];
    }

    public function testVencimentoNuncaFicaAntesDaCompetencia(): void
    {
        $competencia = Competencia::deTexto('2026-08');

        $vencimento = (new CalculadoraVencimento())->calcular($competencia, 1, 0);

        self::assertGreaterThanOrEqual($competencia->primeiroDia(), $vencimento);
    }

    #[DataProvider('parametrosInvalidos')]
    public function testRecusaParametrosForaDosLimitesDoCadastro(int $dia, int $meses): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new CalculadoraVencimento())->calcular(Competencia::deTexto('2026-01'), $dia, $meses);
    }

    /**
     * Os limites espelham os CHECKs de tipos_obrigacao (dia 1–31, meses 0–12).
     *
     * @return array<string, array{int, int}>
     */
    public static function parametrosInvalidos(): array
    {
        return [
            'dia zero' => [0, 1],
            'dia 32' => [32, 1],
            'meses negativos' => [10, -1],
            'treze meses' => [10, 13],
        ];
    }
}
