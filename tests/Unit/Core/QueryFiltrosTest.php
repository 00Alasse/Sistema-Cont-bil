<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\QueryFiltros;
use PHPUnit\Framework\TestCase;

final class QueryFiltrosTest extends TestCase
{
    public function testEscapaCaracteresEspeciaisDoLikeComoLiterais(): void
    {
        self::assertSame('50\\%\\_off', QueryFiltros::like('50%_off'));
        self::assertSame('pasta\\\\arquivo', QueryFiltros::like('pasta\\arquivo'));
    }

    public function testAceitaColunaPermitidaComDirecaoValida(): void
    {
        self::assertSame(
            'nome DESC',
            QueryFiltros::ordenacao('nome DESC', ['id', 'nome'], 'id ASC')
        );
    }

    public function testOrdenacaoMaliciosaOuInvalidaUsaPadrao(): void
    {
        $padrao = 'id ASC';

        self::assertSame(
            $padrao,
            QueryFiltros::ordenacao('id;DROP TABLE usuarios', ['id', 'nome'], $padrao)
        );
        self::assertSame(
            $padrao,
            QueryFiltros::ordenacao('email DESC', ['id', 'nome'], $padrao)
        );
        self::assertSame(
            $padrao,
            QueryFiltros::ordenacao('nome SIDEWAYS', ['id', 'nome'], $padrao)
        );
    }
}
