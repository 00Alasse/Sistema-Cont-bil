<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Controllers\InicioController;
use App\Core\Config;
use App\Core\Container;
use App\Core\HeadersSeguranca;
use App\Core\Kernel;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\View;
use PHPUnit\Framework\TestCase;
use RuntimeException;

require_once dirname(__DIR__, 3) . '/app/Core/helpers.php';

final class HeadersSegurancaTest extends TestCase
{
    private const ESPERADOS = [
        'X-Frame-Options' => 'DENY',
        'X-Content-Type-Options' => 'nosniff',
        'Referrer-Policy' => 'same-origin',
        'Content-Security-Policy' => "default-src 'self'; script-src 'self'; style-src 'self'; "
            . "img-src 'self' data:; frame-ancestors 'none'; form-action 'self'",
    ];

    private string $diretorioLogs;

    protected function setUp(): void
    {
        Config::definir(['app' => ['debug' => false, 'timezone' => 'America/Sao_Paulo']]);
        $this->diretorioLogs = sys_get_temp_dir() . '/tributo-headers-' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->diretorioLogs . '/*.log') ?: [] as $arquivo) {
            unlink($arquivo);
        }
        if (is_dir($this->diretorioLogs)) {
            rmdir($this->diretorioLogs);
        }
    }

    public function testAplicaCadaHeaderComOValorExato(): void
    {
        $resposta = HeadersSeguranca::aplicar(Response::html('ok'));

        $this->assertHeaders($resposta);
        self::assertSame('ok', $resposta->corpo);
        self::assertSame(200, $resposta->status);
    }

    public function testSubstituiHeaderExistenteSemDuplicar(): void
    {
        $resposta = HeadersSeguranca::aplicar(
            Response::html('ok')->comHeader('x-frame-options', 'SAMEORIGIN')
        );

        $this->assertHeaders($resposta);
        $nomes = array_map('strtolower', array_keys($resposta->headers));
        self::assertSame(1, count(array_keys($nomes, 'x-frame-options', true)));
    }

    public function testHeadersPresentesEmRespostaDeSucessoDoKernel(): void
    {
        $router = new Router();
        $router->adicionar('GET', '/', [InicioController::class, 'index'], []);

        $this->assertHeaders($this->kernel($router)->tratar(new Request('GET', '/')));
    }

    public function testHeadersPresentesNasRespostasDeErroDoKernel(): void
    {
        $router = new Router();
        $router->adicionar('GET', '/', [InicioController::class, 'index'], []);
        $router->adicionar('POST', '/enviar', [InicioController::class, 'index'], []);
        $router->adicionar('GET', '/falha', [ControllerQueFalha::class, 'falhar'], []);

        $kernel = $this->kernel($router);
        $casos = [
            404 => new Request('GET', '/nao-existe'),
            405 => new Request('PUT', '/'),
            419 => new Request('POST', '/enviar'),
            500 => new Request('GET', '/falha'),
        ];

        foreach ($casos as $status => $request) {
            $resposta = $kernel->tratar($request);
            self::assertSame($status, $resposta->status);
            $this->assertHeaders($resposta);
        }
    }

    public function testHeadersPresentesNaFalhaDeInicializacao(): void
    {
        $resposta = Kernel::responderFalhaInicializacao(
            new RuntimeException('x'),
            dirname(__DIR__, 3) . '/app/Views',
            $this->diretorioLogs
        );

        $this->assertHeaders($resposta);
    }

    private function assertHeaders(Response $resposta): void
    {
        foreach (self::ESPERADOS as $nome => $valor) {
            self::assertSame($valor, $resposta->headers[$nome] ?? null, 'Header ' . $nome);
        }
    }

    private function kernel(Router $router): Kernel
    {
        return new Kernel(
            $router,
            new Container(),
            new View(dirname(__DIR__, 3) . '/app/Views'),
            $this->diretorioLogs
        );
    }
}

final class ControllerQueFalha
{
    public function falhar(Request $request): Response
    {
        throw new RuntimeException('falha interna');
    }
}
