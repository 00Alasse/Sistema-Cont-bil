<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Router;
use App\Exceptions\MetodoNaoPermitidoException;
use App\Exceptions\NaoEncontradoException;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    public function testResolveRotaEstatica(): void
    {
        $router = new Router();
        $handler = [ControllerDeTeste::class, 'index'];
        $router->adicionar('GET', '/empresas', $handler, []);

        $rota = $router->resolver('GET', '/empresas/');

        self::assertSame($handler, $rota->handler);
        self::assertSame([], $rota->parametros);
        self::assertSame([], $rota->middlewares);
    }

    public function testResolveGetQuandoGetEPostEstaoRegistradosNoMesmoCaminho(): void
    {
        $router = new Router();
        $handlerGet = [ControllerDeTeste::class, 'index'];
        $handlerPost = [ControllerDeTeste::class, 'mostrar'];
        $router->adicionar('GET', '/empresas', $handlerGet, []);
        $router->adicionar('POST', '/empresas', $handlerPost, []);

        $rota = $router->resolver('GET', '/empresas');

        self::assertSame($handlerGet, $rota->handler);
    }

    public function testResolvePostQuandoGetEPostEstaoRegistradosNoMesmoCaminho(): void
    {
        $router = new Router();
        $handlerGet = [ControllerDeTeste::class, 'index'];
        $handlerPost = [ControllerDeTeste::class, 'mostrar'];
        $router->adicionar('GET', '/empresas', $handlerGet, []);
        $router->adicionar('POST', '/empresas', $handlerPost, []);

        $rota = $router->resolver('POST', '/empresas');

        self::assertSame($handlerPost, $rota->handler);
    }

    public function testMetodoNaoPermitidoIncluiTodosOsMetodosDoMesmoCaminho(): void
    {
        $router = new Router();
        $router->adicionar('GET', '/empresas', [ControllerDeTeste::class, 'index'], []);
        $router->adicionar('POST', '/empresas', [ControllerDeTeste::class, 'mostrar'], []);

        try {
            $router->resolver('PUT', '/empresas');
            self::fail('Era esperado um erro 405 para o método PUT.');
        } catch (MetodoNaoPermitidoException $exception) {
            self::assertSame(['GET', 'POST'], $exception->metodosPermitidos);
        }
    }

    public function testRotaEstaticaIncompativelNaoImpedeResolucaoDeOutraRotaValida(): void
    {
        $router = new Router();
        $handlerDinamico = [ControllerDeTeste::class, 'mostrar'];
        $router->adicionar('GET', '/empresas/42', [ControllerDeTeste::class, 'index'], []);
        $router->adicionar('POST', '/empresas/{id}', $handlerDinamico, []);

        $rota = $router->resolver('POST', '/empresas/42');

        self::assertSame($handlerDinamico, $rota->handler);
        self::assertSame(['id' => 42], $rota->parametros);
    }

    public function testResolveParametroNumericoComoInteiroEPreservaMiddleware(): void
    {
        $router = new Router();
        $handler = [ControllerDeTeste::class, 'mostrar'];
        $middlewares = ['auth', 'perfil:ADMIN'];
        $router->adicionar('GET', '/empresas/{id}', $handler, $middlewares);

        $rota = $router->resolver('GET', '/empresas/42');

        self::assertSame($handler, $rota->handler);
        self::assertSame(['id' => 42], $rota->parametros);
        self::assertSame($middlewares, $rota->middlewares);
    }

    public function testParametroNaoNumericoResultaEm404(): void
    {
        $router = new Router();
        $router->adicionar('GET', '/empresas/{id}', [ControllerDeTeste::class, 'mostrar'], []);

        $this->expectException(NaoEncontradoException::class);
        $router->resolver('GET', '/empresas/abc');
    }

    public function testCaminhoInexistenteResultaEm404(): void
    {
        $router = new Router();

        $this->expectException(NaoEncontradoException::class);
        $router->resolver('GET', '/inexistente');
    }

    public function testCaminhoExistenteComMetodoIncorretoResultaEm405(): void
    {
        $router = new Router();
        $router->adicionar('GET', '/empresas/{id}', [ControllerDeTeste::class, 'mostrar'], []);

        $this->expectException(MetodoNaoPermitidoException::class);
        $router->resolver('POST', '/empresas/42');
    }

    public function testRotaDuplicadaEhRejeitadaSemDiferenciarMetodoPorMaiusculas(): void
    {
        $router = new Router();
        $handler = [ControllerDeTeste::class, 'index'];
        $router->adicionar('GET', '/empresas', $handler, []);

        $this->expectException(LogicException::class);
        $router->adicionar('get', '/empresas/', $handler, []);
    }

    public function testPadraoComParametroDuplicadoEhRejeitado(): void
    {
        $router = new Router();

        $this->expectException(InvalidArgumentException::class);
        $router->adicionar('GET', '/{id}/{id}', [ControllerDeTeste::class, 'index'], []);
    }
}

final class ControllerDeTeste
{
    public function index(): void
    {
    }

    public function mostrar(int $id): void
    {
    }
}
