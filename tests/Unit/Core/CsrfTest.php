<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Csrf;
use App\Core\Session;
use App\Support\RelogioFixo;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class CsrfTest extends TestCase
{
    protected function tearDown(): void
    {
        Session::definirAtual(null);
    }

    public function testTokenTem32BytesEmHexadecimal(): void
    {
        $this->usarSessao();

        self::assertMatchesRegularExpression('/\A[0-9a-f]{64}\z/', Csrf::token());
    }

    public function testTokenEEstavelDentroDaSessaoEDiferenteEntreSessoes(): void
    {
        $this->usarSessao();
        $primeiro = Csrf::token();
        self::assertSame($primeiro, Csrf::token());

        $this->usarSessao();
        self::assertNotSame($primeiro, Csrf::token());
    }

    public function testAceitaTokenValido(): void
    {
        $this->usarSessao();

        self::assertTrue(Csrf::validar(Csrf::token()));
    }

    public function testRejeitaTokenInvalido(): void
    {
        $this->usarSessao();
        $token = Csrf::token();

        self::assertFalse(Csrf::validar(str_repeat('0', 64)));
        self::assertFalse(Csrf::validar($token . 'x'));
        self::assertFalse(Csrf::validar(substr($token, 0, 10)));
    }

    public function testRejeitaTokenAusenteOuVazio(): void
    {
        $this->usarSessao();
        Csrf::token();

        self::assertFalse(Csrf::validar(null));
        self::assertFalse(Csrf::validar(''));
    }

    public function testRejeitaTokenDeOutraSessao(): void
    {
        $this->usarSessao();
        $tokenDaOutra = Csrf::token();

        $this->usarSessao();
        Csrf::token();

        self::assertFalse(Csrf::validar($tokenDaOutra));
    }

    public function testRejeitaQuandoASessaoAindaNaoTemToken(): void
    {
        $this->usarSessao();

        self::assertFalse(Csrf::validar(str_repeat('a', 64)));
    }

    private function usarSessao(): void
    {
        $dados = [];
        $sessao = Session::emArray($dados, new RelogioFixo(new DateTimeImmutable('2026-01-10 10:00:00')), 30);
        $sessao->iniciar();
        Session::definirAtual($sessao);
    }
}
