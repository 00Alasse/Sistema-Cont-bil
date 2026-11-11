<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Paginacao;
use App\Core\Request;
use PHPUnit\Framework\TestCase;

final class PaginacaoTest extends TestCase
{
    public function testPaginaZeroNegativaETextoInvalidoVoltamParaUma(): void
    {
        foreach ([0, -3, 'texto', '', '1.5'] as $valor) {
            $paginacao = Paginacao::deRequest(
                new Request('GET', '/', ['pagina' => $valor])
            );

            self::assertSame(1, $paginacao->pagina);
        }
    }

    public function testConvertePaginaValidaInformadaComoTexto(): void
    {
        $paginacao = Paginacao::deRequest(
            new Request('GET', '/', ['pagina' => '4'])
        );

        self::assertSame(4, $paginacao->pagina);
    }

    public function testLimitaItensPorPaginaAUmIntervaloSeguro(): void
    {
        $request = new Request('GET', '/');

        self::assertSame(1, Paginacao::deRequest($request, 0)->porPagina);
        self::assertSame(100, Paginacao::deRequest($request, 101)->porPagina);
        self::assertSame(20, Paginacao::deRequest($request)->porPagina);
    }
}
