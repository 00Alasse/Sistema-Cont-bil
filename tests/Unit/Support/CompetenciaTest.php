<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\Competencia;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CompetenciaTest extends TestCase
{
    public function testLeTextoNoFormatoAnoMes(): void
    {
        $competencia = Competencia::deTexto('2026-03');

        self::assertSame(2026, $competencia->ano);
        self::assertSame(3, $competencia->mes);
        self::assertSame('2026-03', $competencia->comoTexto());
    }

    public function testAceitaEspacosNasPontas(): void
    {
        self::assertSame('2026-11', Competencia::deTexto(" 2026-11\n")->comoTexto());
    }

    #[DataProvider('textosInvalidos')]
    public function testRecusaTextoInvalido(string $texto): void
    {
        $this->expectException(InvalidArgumentException::class);

        Competencia::deTexto($texto);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function textosInvalidos(): array
    {
        return [
            'vazio' => [''],
            'mês 13' => ['2026-13'],
            'mês zero' => ['2026-00'],
            'sem zero à esquerda' => ['2026-3'],
            'com dia' => ['2026-03-01'],
            'ano curto' => ['26-03'],
            'formato brasileiro' => ['03/2026'],
            'texto' => ['março'],
        ];
    }

    public function testLeCompetenciaDeUmaDataDoBanco(): void
    {
        self::assertSame('2026-07', Competencia::deData('2026-07-01')->comoTexto());
        // Qualquer dia do mês representa a mesma competência.
        self::assertSame('2026-07', Competencia::deData('2026-07-31')->comoTexto());
    }

    public function testRecusaDataInvalida(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Competencia::deData('2026-02-30');
    }

    public function testPrimeiroDiaEhSempreODia01(): void
    {
        $primeiroDia = Competencia::deTexto('2026-02')->primeiroDia();

        self::assertSame('2026-02-01', $primeiroDia->format('Y-m-d'));
        self::assertSame('00:00:00', $primeiroDia->format('H:i:s'));
    }

    #[DataProvider('ultimosDias')]
    public function testUltimoDiaRespeitaOTamanhoDoMes(string $competencia, string $esperado): void
    {
        self::assertSame($esperado, Competencia::deTexto($competencia)->ultimoDia()->format('Y-m-d'));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function ultimosDias(): array
    {
        return [
            'janeiro' => ['2026-01', '2026-01-31'],
            'abril' => ['2026-04', '2026-04-30'],
            'fevereiro comum' => ['2026-02', '2026-02-28'],
            'fevereiro bissexto' => ['2028-02', '2028-02-29'],
            'dezembro' => ['2026-12', '2026-12-31'],
        ];
    }

    #[DataProvider('somas')]
    public function testSomaMeses(string $inicio, int $meses, string $esperado): void
    {
        self::assertSame($esperado, Competencia::deTexto($inicio)->somarMeses($meses)->comoTexto());
    }

    /**
     * @return array<string, array{string, int, string}>
     */
    public static function somas(): array
    {
        return [
            'zero meses' => ['2026-05', 0, '2026-05'],
            'um mês' => ['2026-05', 1, '2026-06'],
            'virada de ano' => ['2026-12', 1, '2027-01'],
            'doze meses' => ['2026-03', 12, '2027-03'],
            'mais de um ano' => ['2026-11', 14, '2028-01'],
        ];
    }

    public function testSomaNaoAlteraACompetenciaOriginal(): void
    {
        $original = Competencia::deTexto('2026-01');

        $original->somarMeses(5);

        self::assertSame('2026-01', $original->comoTexto());
    }

    public function testRecusaSomaNegativa(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Competencia::deTexto('2026-01')->somarMeses(-1);
    }

    public function testPrimeiroDiaEhComparavelComDateTime(): void
    {
        self::assertEquals(
            new DateTimeImmutable('2026-09-01 00:00:00'),
            Competencia::deTexto('2026-09')->primeiroDia()
        );
    }
}
