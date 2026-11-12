<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\UsuarioLogado;
use App\Exceptions\AcessoNegadoException;
use App\Middleware\PerfilMiddleware;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PerfilMiddlewareTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function permitidos(): array
    {
        return [
            'admin em ADMIN' => ['ADMIN', 'ADMIN'],
            'admin em lista' => ['ADMIN,COLABORADOR', 'ADMIN'],
            'colaborador em lista' => ['ADMIN,COLABORADOR', 'COLABORADOR'],
            'cliente em CLIENTE' => ['CLIENTE', 'CLIENTE'],
        ];
    }

    #[DataProvider('permitidos')]
    public function testPerfilPermitidoSegue(string $config, string $perfil): void
    {
        $resposta = (new PerfilMiddleware($config))->tratar($this->requisicao($perfil), $this->proximo());

        self::assertSame(200, $resposta->status);
        self::assertSame('passou', $resposta->corpo);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function negados(): array
    {
        return [
            'colaborador em ADMIN' => ['ADMIN', 'COLABORADOR'],
            'cliente em ADMIN' => ['ADMIN', 'CLIENTE'],
            'cliente em lista do escritório' => ['ADMIN,COLABORADOR', 'CLIENTE'],
            'admin em CLIENTE' => ['CLIENTE', 'ADMIN'],
        ];
    }

    #[DataProvider('negados')]
    public function testPerfilNaoPermitidoLancaAcessoNegadoSemChamarOProximo(string $config, string $perfil): void
    {
        $chamado = false;

        try {
            (new PerfilMiddleware($config))->tratar($this->requisicao($perfil), function () use (&$chamado): Response {
                $chamado = true;

                return Response::html('x');
            });
            self::fail('Era esperada AcessoNegadoException.');
        } catch (AcessoNegadoException) {
            self::assertFalse($chamado);
        }
    }

    public function testSemUsuarioNaRequisicaoNaoLiberaAcesso(): void
    {
        $this->expectException(LogicException::class);

        (new PerfilMiddleware('ADMIN'))->tratar(new Request('GET', '/'), $this->proximo());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidas(): array
    {
        return [
            'vazia' => [''],
            'perfil desconhecido' => ['SUPER'],
            'minúsculas' => ['admin'],
            'item vazio' => ['ADMIN,'],
            'espaço' => ['ADMIN, COLABORADOR'],
            'separador errado' => ['ADMIN;COLABORADOR'],
        ];
    }

    #[DataProvider('invalidas')]
    public function testConfiguracaoInvalidaFalhaAoCriar(string $config): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PerfilMiddleware($config);
    }

    private function requisicao(string $perfil): Request
    {
        $usuario = new UsuarioLogado(1, 1, $perfil, $perfil === 'CLIENTE' ? 1 : null, 'Teste', []);

        return (new Request('GET', '/'))->comUsuario($usuario);
    }

    /**
     * @return callable(Request): Response
     */
    private function proximo(): callable
    {
        return static fn (Request $r): Response => Response::html('passou');
    }
}
