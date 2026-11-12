<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\UsuarioLogado;
use App\Exceptions\ModuloInativoException;
use App\Middleware\ModuloMiddleware;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ModuloMiddlewareTest extends TestCase
{
    public function testModuloAtivoSegue(): void
    {
        $resposta = (new ModuloMiddleware('BPO_FINANCEIRO'))
            ->tratar($this->requisicao(1, ['BPO_FINANCEIRO', 'CONSULTIVO']), $this->proximo());

        self::assertSame('passou', $resposta->corpo);
    }

    public function testModuloInativoLancaExcecaoSemChamarOProximo(): void
    {
        $chamado = false;

        try {
            (new ModuloMiddleware('BPO_FINANCEIRO'))->tratar($this->requisicao(2, []), function () use (&$chamado): Response {
                $chamado = true;

                return Response::html('x');
            });
            self::fail('Era esperada ModuloInativoException.');
        } catch (ModuloInativoException) {
            self::assertFalse($chamado);
        }
    }

    public function testTerOutroModuloNaoBasta(): void
    {
        $this->expectException(ModuloInativoException::class);

        (new ModuloMiddleware('BPO_FINANCEIRO'))->tratar($this->requisicao(1, ['CONSULTIVO']), $this->proximo());
    }

    public function testEscritoriosDiferentesSaoIndependentes(): void
    {
        $middleware = new ModuloMiddleware('CONSULTIVO');

        $comModulo = $middleware->tratar($this->requisicao(1, ['CONSULTIVO']), $this->proximo());
        self::assertSame('passou', $comModulo->corpo);

        $this->expectException(ModuloInativoException::class);
        $middleware->tratar($this->requisicao(2, []), $this->proximo());
    }

    public function testModulosEnviadosPeloClienteSaoIgnorados(): void
    {
        $requisicao = (new Request('GET', '/', ['modulos' => ['BPO_FINANCEIRO']], ['modulos' => 'BPO_FINANCEIRO']))
            ->comUsuario(new UsuarioLogado(1, 2, 'ADMIN', null, 'Teste', []));

        $this->expectException(ModuloInativoException::class);

        (new ModuloMiddleware('BPO_FINANCEIRO'))->tratar($requisicao, $this->proximo());
    }

    public function testSemUsuarioNaRequisicaoNaoLiberaAcesso(): void
    {
        $this->expectException(LogicException::class);

        (new ModuloMiddleware('BPO_FINANCEIRO'))->tratar(new Request('GET', '/'), $this->proximo());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidos(): array
    {
        return [
            'vazio' => [''],
            'inexistente' => ['FISCAL'],
            'minúsculas' => ['bpo_financeiro'],
            'lista' => ['BPO_FINANCEIRO,CONSULTIVO'],
            'com espaço' => [' BPO_FINANCEIRO'],
        ];
    }

    #[DataProvider('invalidos')]
    public function testConfiguracaoInvalidaFalhaAoCriar(string $config): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ModuloMiddleware($config);
    }

    /**
     * @param list<string> $modulos
     */
    private function requisicao(int $escritorioId, array $modulos): Request
    {
        $usuario = new UsuarioLogado(1, $escritorioId, 'ADMIN', null, 'Teste', $modulos);

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
