<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Core\Config;
use App\Support\RelogioFixo;
use App\Support\RelogioSistema;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

final class RelogioTest extends TestCase
{
    public function testRelogioFixoMantemDataEHoraRecebidas(): void
    {
        $data = new DateTimeImmutable(
            '2026-03-15 14:30:45',
            new DateTimeZone('America/Sao_Paulo')
        );
        $relogio = new RelogioFixo($data);

        self::assertSame('2026-03-15', $relogio->hoje()->format('Y-m-d'));
        self::assertSame('14:30:45', $relogio->agora()->format('H:i:s'));
        self::assertSame('America/Sao_Paulo', $relogio->agora()->getTimezone()->getName());
    }

    public function testRelogioFixoNaoAlteraOValorOriginal(): void
    {
        $data = new DateTimeImmutable('2026-03-15 14:30:45');
        $relogio = new RelogioFixo($data);

        $hoje = $relogio->hoje();

        self::assertSame('00:00:00', $hoje->format('H:i:s'));
        self::assertSame('2026-03-15 14:30:45', $data->format('Y-m-d H:i:s'));
    }

    public function testRelogioSistemaUsaFusoDeSaoPaulo(): void
    {
        Config::definir(['app' => ['timezone' => 'America/Sao_Paulo']]);
        $relogio = new RelogioSistema();

        self::assertSame('America/Sao_Paulo', $relogio->agora()->getTimezone()->getName());
        self::assertSame(
            $relogio->agora()->format('Y-m-d'),
            $relogio->hoje()->format('Y-m-d')
        );
    }
}
