<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Config;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3) . '/app/Core/helpers.php';

final class HelpersTest extends TestCase
{
    protected function setUp(): void
    {
        Config::definir(['app' => ['base_path' => '/tributo/']]);
    }

    public function testEscapaHtmlEProtegeContraXss(): void
    {
        self::assertSame(
            '&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;&amp;&#039;',
            e('<script>alert("x")</script>&\'')
        );
    }

    public function testEscapaUtf8InvalidoETrataNuloComoVazio(): void
    {
        self::assertSame('�', e("\xB1"));
        self::assertSame('', e(null));
    }

    public function testGeraUrlComBasePathEQuery(): void
    {
        self::assertSame('/tributo/empresas?pagina=2', url('/empresas', ['pagina' => 2]));
    }

    public function testGeraUrlSemBasePath(): void
    {
        Config::definir(['app' => ['base_path' => '']]);

        self::assertSame('/empresas', url('empresas'));
    }

    public function testGeraUrlParaRaizSemBarraDuplicada(): void
    {
        self::assertSame('/tributo/', url('/'));
    }

    public function testRenderizaMensagemDeErroEscapada(): void
    {
        self::assertSame(
            '<span class="erro-campo">&lt;inválido&gt;</span>',
            erro_campo(['nome' => ['<inválido>']], 'nome')
        );
    }

    public function testFormataDataBrEDevolveVazioParaDataInvalida(): void
    {
        self::assertSame('10/11/2026', data_br('2026-11-10'));
        self::assertSame('', data_br('data-inválida'));
        self::assertSame('', data_br(null));
    }
}
