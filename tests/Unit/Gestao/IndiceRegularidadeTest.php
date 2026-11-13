<?php

declare(strict_types=1);

namespace Tests\Unit\Gestao;

use App\Core\Config;
use App\Services\Gestao\IndiceRegularidade;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Cálculo puro do Índice de Regularidade (ARCHITECTURE §10.4), sem banco.
 */
final class IndiceRegularidadeTest extends TestCase
{
    private const REGRAS = [
        'janela_meses' => 12,
        'peso_atrasada' => 10,
        'peso_documento_vencido' => 5,
        'limite_vermelho' => 60,
        'limite_verde' => 85,
        'dias_atraso_critico' => 30,
    ];

    public function testConfiguracaoTemExatamenteOsValoresDaDocumentacao(): void
    {
        self::assertSame(self::REGRAS, Config::regras('regularidade'));
    }

    public function testEmpresaPerfeitaTemScore100Verde(): void
    {
        $resultado = $this->calcular(total: 10, noPrazo: 10);

        self::assertSame(100, $resultado['score']);
        self::assertSame('verde', $resultado['semaforo']);
        self::assertSame(['Pontualidade de 100% em 12 meses'], $resultado['motivos']);
        self::assertSame([
            'total' => 10,
            'no_prazo' => 10,
            'pontualidade' => 100,
            'atrasadas' => 0,
            'atrasadas_criticas' => 0,
            'docs_vencidos' => 0,
        ], $resultado['contadores']);
    }

    /**
     * total = 100 deixa a pontualidade igual a no_prazo, para ir direto às fronteiras.
     *
     * @return iterable<string, array{int, string}>
     */
    public static function fronteirasDoScore(): iterable
    {
        yield '59 é vermelho' => [59, 'vermelho'];
        yield '60 é amarelo' => [60, 'amarelo'];
        yield '84 é amarelo' => [84, 'amarelo'];
        yield '85 é verde' => [85, 'verde'];
    }

    #[DataProvider('fronteirasDoScore')]
    public function testFronteirasDoSemaforo(int $score, string $semaforo): void
    {
        $resultado = $this->calcular(total: 100, noPrazo: $score);

        self::assertSame($score, $resultado['score']);
        self::assertSame($semaforo, $resultado['semaforo']);
    }

    public function testScore85ComAtrasadaNaoEhVerde(): void
    {
        // 95% − 10 (1 atrasada) = 85: o score permitiria verde, mas há atrasada.
        $resultado = $this->calcular(total: 100, noPrazo: 95, atrasadas: 1);

        self::assertSame(85, $resultado['score']);
        self::assertSame('amarelo', $resultado['semaforo']);
    }

    public function testAtrasadaHaMaisDe30DiasForcaVermelhoMesmoComScoreAlto(): void
    {
        $resultado = $this->calcular(total: 100, noPrazo: 100, atrasadas: 1, criticas: 1);

        self::assertSame(90, $resultado['score']);
        self::assertSame('vermelho', $resultado['semaforo']);
    }

    public function testScoreNuncaFicaAbaixoDeZero(): void
    {
        $resultado = $this->calcular(total: 10, noPrazo: 2, atrasadas: 8);

        // 20% − 80 = −60 → 0.
        self::assertSame(0, $resultado['score']);
        self::assertSame('vermelho', $resultado['semaforo']);
    }

    public function testDocumentosVencidosDescontam5PontosCada(): void
    {
        $resultado = $this->calcular(total: 10, noPrazo: 10, docs: 3);

        self::assertSame(85, $resultado['score']);
        self::assertSame('verde', $resultado['semaforo']);
    }

    /**
     * @return iterable<string, array{int, int, int}>
     */
    public static function arredondamentos(): iterable
    {
        yield '157/200 = 78,5% → 79' => [200, 157, 79];
        yield '1/3 = 33,3% → 33' => [3, 1, 33];
        yield '2/3 = 66,7% → 67' => [3, 2, 67];
        yield '1/8 = 12,5% → 13' => [8, 1, 13];
        yield '0/5 = 0%' => [5, 0, 0];
    }

    #[DataProvider('arredondamentos')]
    public function testArredondamentoDaPontualidade(int $total, int $noPrazo, int $esperado): void
    {
        $resultado = $this->calcular(total: $total, noPrazo: $noPrazo);

        self::assertSame($esperado, $resultado['contadores']['pontualidade']);
        self::assertSame($esperado, $resultado['score']);
    }

    public function testSemHistorico(): void
    {
        $resultado = $this->calcular(total: 0, noPrazo: 0);

        self::assertNull($resultado['score']);
        self::assertSame('sem_historico', $resultado['semaforo']);
        self::assertNull($resultado['contadores']['pontualidade']);
        self::assertSame(['Sem obrigações vencidas nos últimos 12 meses'], $resultado['motivos']);
    }

    public function testSemHistoricoComAtrasoCriticoFicaVermelho(): void
    {
        // Atrasada vencida antes da janela: fora do total, mas é atraso crítico (D1).
        $resultado = $this->calcular(total: 0, noPrazo: 0, atrasadas: 1, criticas: 1);

        self::assertNull($resultado['score']);
        self::assertSame('vermelho', $resultado['semaforo']);
        self::assertSame([
            '1 obrigação atrasada',
            'Obrigação atrasada há mais de 30 dias',
            'Sem obrigações vencidas nos últimos 12 meses',
        ], $resultado['motivos']);
    }

    public function testMotivosNoPluralEmOrdem(): void
    {
        $resultado = $this->calcular(total: 50, noPrazo: 39, atrasadas: 3, criticas: 2, docs: 2);

        self::assertSame([
            '3 obrigações atrasadas',
            '2 obrigações atrasadas há mais de 30 dias',
            '2 documentos vencidos',
            'Pontualidade de 78% em 12 meses',
        ], $resultado['motivos']);
    }

    public function testMotivosNoSingular(): void
    {
        $resultado = $this->calcular(total: 4, noPrazo: 3, atrasadas: 1, docs: 1);

        self::assertSame([
            '1 obrigação atrasada',
            '1 documento vencido',
            'Pontualidade de 75% em 12 meses',
        ], $resultado['motivos']);
    }

    public function testContadoresIncoerentesSaoRecusados(): void
    {
        $indice = new IndiceRegularidade(self::REGRAS);

        foreach ([
            ['total' => 1, 'no_prazo' => 2, 'atrasadas' => 0, 'atrasadas_criticas' => 0, 'docs_vencidos' => 0],
            ['total' => 1, 'no_prazo' => 1, 'atrasadas' => 0, 'atrasadas_criticas' => 1, 'docs_vencidos' => 0],
            ['total' => -1, 'no_prazo' => 0, 'atrasadas' => 0, 'atrasadas_criticas' => 0, 'docs_vencidos' => 0],
        ] as $contadores) {
            try {
                $indice->calcular($contadores);
                self::fail('Contadores incoerentes deveriam ser recusados.');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testRegraAusenteNaConfiguracaoEhRecusada(): void
    {
        $regras = self::REGRAS;
        unset($regras['limite_verde']);

        $this->expectException(InvalidArgumentException::class);
        new IndiceRegularidade($regras);
    }

    public function testDatasDeReferencia(): void
    {
        $datas = (new IndiceRegularidade(self::REGRAS))->datasDeReferencia(new DateTimeImmutable('2026-03-10 14:30:00'));

        self::assertSame([
            'hoje' => '2026-03-10',
            'inicio' => '2025-03-10',
            'fim' => '2026-03-09',
            // vencimento < critico ⇔ vencido há mais de 30 dias.
            'critico' => '2026-02-08',
        ], $datas);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function iniciosDaJanela(): iterable
    {
        yield '29/02/2028 → 28/02/2027' => ['2028-02-29', '2027-02-28'];
        yield '31/03/2026 → 31/03/2025' => ['2026-03-31', '2025-03-31'];
        yield '28/02/2025 → 28/02/2024' => ['2025-02-28', '2024-02-28'];
        yield '01/01/2026 → 01/01/2025' => ['2026-01-01', '2025-01-01'];
    }

    #[DataProvider('iniciosDaJanela')]
    public function testInicioDaJanelaUsaOUltimoDiaValidoDoMes(string $hoje, string $inicio): void
    {
        $datas = (new IndiceRegularidade(self::REGRAS))->datasDeReferencia(new DateTimeImmutable($hoje));

        self::assertSame($inicio, $datas['inicio']);
    }

    /**
     * @return array<string, mixed>
     */
    private function calcular(int $total, int $noPrazo, int $atrasadas = 0, int $criticas = 0, int $docs = 0): array
    {
        return (new IndiceRegularidade(self::REGRAS))->calcular([
            'total' => $total,
            'no_prazo' => $noPrazo,
            'atrasadas' => $atrasadas,
            'atrasadas_criticas' => $criticas,
            'docs_vencidos' => $docs,
        ]);
    }
}
