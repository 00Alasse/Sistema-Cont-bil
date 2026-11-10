<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Modulos;
use App\Core\Router;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ModulosTest extends TestCase
{
    private string $diretorio;

    protected function setUp(): void
    {
        $this->diretorio = sys_get_temp_dir() . '/tributo-modulos-' . bin2hex(random_bytes(6));
        mkdir($this->diretorio);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->diretorio . '/*.php') ?: [] as $arquivo) {
            unlink($arquivo);
        }
        rmdir($this->diretorio);
    }

    public function testAcrescentaMiddlewareDoModuloContratavelPreservandoOsDemais(): void
    {
        $this->gravarModulo('financeiro', $this->configuracao('BPO_FINANCEIRO', ['auth']));
        $router = new Router();

        $carregados = (new Modulos($router, $this->diretorio))->carregar();
        $rota = $router->resolver('GET', '/financeiro');

        self::assertArrayHasKey('financeiro', $carregados);
        self::assertSame(['auth', 'modulo:BPO_FINANCEIRO'], $rota->middlewares);
    }

    public function testNaoAcrescentaMiddlewareAmoduloDoPlanoBase(): void
    {
        $this->gravarModulo('nucleo', $this->configuracao(null, []));
        $router = new Router();

        (new Modulos($router, $this->diretorio))->carregar();

        self::assertSame([], $router->resolver('GET', '/financeiro')->middlewares);
    }

    public function testCarregaArquivosEmOrdemAlfabetica(): void
    {
        $this->gravarModulo('zeta', $this->configuracao(null, [], '/zeta'));
        $this->gravarModulo('alfa', $this->configuracao(null, [], '/alfa'));

        $carregados = (new Modulos(new Router(), $this->diretorio))->carregar();

        self::assertSame(['alfa', 'zeta'], array_keys($carregados));
    }

    public function testRejeitaConfiguracaoSemChavesObrigatorias(): void
    {
        $this->gravarModulo('invalido', ['contratavel' => null]);

        $this->expectException(InvalidArgumentException::class);
        (new Modulos(new Router(), $this->diretorio))->carregar();
    }

    public function testRejeitaCodigoDeModuloContratavelDesconhecido(): void
    {
        $this->gravarModulo('invalido', $this->configuracao('OUTRO_MODULO', []));

        $this->expectException(InvalidArgumentException::class);
        (new Modulos(new Router(), $this->diretorio))->carregar();
    }

    public function testRejeitaRotaMalformada(): void
    {
        $configuracao = $this->configuracao(null, []);
        $configuracao['rotas'] = [['GET', '/invalida']];
        $this->gravarModulo('invalido', $configuracao);

        $this->expectException(InvalidArgumentException::class);
        (new Modulos(new Router(), $this->diretorio))->carregar();
    }

    public function testRejeitaArquivoQueNaoRetornaArray(): void
    {
        file_put_contents($this->diretorio . '/invalido.php', '<?php return "inválido";');

        $this->expectException(RuntimeException::class);
        (new Modulos(new Router(), $this->diretorio))->carregar();
    }

    /**
     * @param list<string> $middlewares
     * @return array<string, mixed>
     */
    private function configuracao(?string $contratavel, array $middlewares, string $caminho = '/financeiro'): array
    {
        return [
            'contratavel' => $contratavel,
            'menu' => [],
            'abas_empresa' => [],
            'cartoes_painel' => [],
            'rotas' => [
                ['GET', $caminho, [ControllerDeModuloTeste::class, 'index'], $middlewares],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $configuracao
     */
    private function gravarModulo(string $nome, array $configuracao): void
    {
        file_put_contents(
            $this->diretorio . '/' . $nome . '.php',
            '<?php return ' . var_export($configuracao, true) . ';'
        );
    }
}

final class ControllerDeModuloTeste
{
    public function index(): void
    {
    }
}
