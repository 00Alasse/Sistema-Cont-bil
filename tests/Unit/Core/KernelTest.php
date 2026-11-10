<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Config;
use App\Core\Container;
use App\Core\Kernel;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\View;
use App\Exceptions\AcessoNegadoException;
use App\Exceptions\ConflitoException;
use App\Exceptions\CsrfException;
use App\Exceptions\MetodoNaoPermitidoException;
use App\Exceptions\ModuloInativoException;
use App\Exceptions\NaoEncontradoException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

require_once dirname(__DIR__, 3) . '/app/Core/helpers.php';

final class KernelTest extends TestCase
{
    private string $diretorioLogs;

    protected function setUp(): void
    {
        Config::definir(['app' => ['debug' => false, 'timezone' => 'America/Sao_Paulo']]);
        $this->diretorioLogs = sys_get_temp_dir() . '/tributo-kernel-' . bin2hex(random_bytes(6));
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

    public function testDespachaRequisicaoParaControllerViaContainer(): void
    {
        $router = new Router();
        $router->adicionar('GET', '/empresas/{id}', [ControllerDoKernelTeste::class, 'mostrar'], []);

        $container = new Container();
        $container->definir(ControllerDoKernelTeste::class, new ControllerDoKernelTeste());
        $response = $this->kernel($router, $container)->tratar(new Request('GET', '/empresas/42'));

        self::assertSame(200, $response->status);
        self::assertSame('empresa:42', $response->corpo);
    }

    public function testRotaInicialProvisoriaPassaPeloKernel(): void
    {
        $router = new Router();
        $diretorioModulos = dirname(__DIR__, 3) . '/config/modules';
        (new \App\Core\Modulos($router, $diretorioModulos))->carregar();

        $response = $this->kernel($router)->tratar(new Request('GET', '/'));

        self::assertSame(200, $response->status);
        self::assertStringContainsString('-Tributo!', $response->corpo);
    }

    public function testCaminhoInexistenteRetorna404(): void
    {
        $response = $this->kernel(new Router())->tratar(new Request('GET', '/inexistente'));

        self::assertSame(404, $response->status);
        self::assertStringContainsString('Página não encontrada', $response->corpo);
    }

    public function testMetodoNaoPermitidoRetorna405(): void
    {
        $router = new Router();
        $router->adicionar('GET', '/', [\App\Controllers\InicioController::class, 'index'], []);
        $router->adicionar('POST', '/', [\App\Controllers\InicioController::class, 'index'], []);

        $response = $this->kernel($router)->tratar(new Request('PUT', '/'));

        self::assertSame(405, $response->status);
        self::assertStringContainsString('Método não permitido', $response->corpo);
        self::assertStringContainsString('GET, POST', $response->corpo);
        self::assertSame('GET, POST', $response->headers['Allow']);
    }

    public function testConverteExcecoesConhecidasNosStatusDocumentados(): void
    {
        $casos = [
            [AcessoNegadoException::class, 403, 'erros/403'],
            [ModuloInativoException::class, 403, 'erros/modulo-inativo'],
            [ConflitoException::class, 409, 'erros/409'],
            [CsrfException::class, 419, 'erros/419'],
            [NaoEncontradoException::class, 404, 'erros/404'],
            [MetodoNaoPermitidoException::class, 405, 'erros/405'],
        ];

        foreach ($casos as [$excecao, $status, $pagina]) {
            ControllerDoKernelTeste::$excecao = $this->excecao($excecao);
            $router = new Router();
            $router->adicionar(
                'GET',
                '/',
                [ControllerDoKernelTeste::class, 'lancarExcecao'],
                []
            );
            $container = new Container();
            $response = $this->kernel($router, $container)->tratar(new Request('GET', '/'));

            ControllerDoKernelTeste::$excecao = null;
            self::assertSame($status, $response->status);
            self::assertStringContainsString(
                $pagina === 'erros/modulo-inativo' ? 'Módulo não contratado' : $this->tituloErro($status),
                $response->corpo
            );
        }
    }

    public function testErroInternoNaoVazaDetalhesQuandoDebugDesativado(): void
    {
        $router = new Router();
        $router->adicionar('GET', '/', [ControllerDoKernelTeste::class, 'falhar'], []);

        $response = $this->kernel($router)->tratar(new Request('GET', '/'));

        self::assertSame(500, $response->status);
        self::assertStringNotContainsString('segredo interno', $response->corpo);
        self::assertDirectoryExists($this->diretorioLogs);
        $logs = file_get_contents(glob($this->diretorioLogs . '/*.log')[0]);
        self::assertIsString($logs);
        self::assertStringNotContainsString('segredo interno', $logs);
    }

    public function testFalhaDeInicializacaoRetorna500GenericoERegistraSemDetalhes(): void
    {
        $erro = new RuntimeException('credencial-super-secreta');
        $response = Kernel::responderFalhaInicializacao(
            $erro,
            dirname(__DIR__, 3) . '/app/Views',
            $this->diretorioLogs
        );

        self::assertSame(500, $response->status);
        self::assertStringContainsString('Ocorreu um erro inesperado.', $response->corpo);
        self::assertStringNotContainsString('credencial-super-secreta', $response->corpo);
        self::assertDirectoryExists($this->diretorioLogs);
        $arquivos = glob($this->diretorioLogs . '/*.log');
        self::assertIsArray($arquivos);
        self::assertCount(1, $arquivos);
        $logs = file_get_contents($arquivos[0]);
        self::assertIsString($logs);
        self::assertStringNotContainsString('credencial-super-secreta', $logs);
    }

    public function testFalhaAoRenderizarErroNaInicializacaoUsaRespostaGenerica(): void
    {
        $diretorioViewsInexistente = $this->diretorioLogs . '/views-inexistentes';
        $response = Kernel::responderFalhaInicializacao(
            new RuntimeException('erro privado'),
            $diretorioViewsInexistente,
            $this->diretorioLogs
        );

        self::assertSame(500, $response->status);
        self::assertStringContainsString('Ocorreu um erro inesperado.', $response->corpo);
        self::assertStringNotContainsString('erro privado', $response->corpo);
    }

    public function testFalhaNoAutoloaderPeloFrontControllerRetorna500SemExporDetalhes(): void
    {
        $raizTemporaria = sys_get_temp_dir() . '/tributo-front-' . bin2hex(random_bytes(6));
        $diretorioPublico = $raizTemporaria . '/public';
        $diretorioVendor = $raizTemporaria . '/vendor';
        mkdir($diretorioPublico, 0700, true);
        mkdir($diretorioVendor, 0700, true);

        $frontController = $diretorioPublico . '/index.php';
        $autoloader = $diretorioVendor . '/autoload.php';
        $runner = $raizTemporaria . '/runner.php';
        $origemFrontController = dirname(__DIR__, 3) . '/public/index.php';

        try {
            self::assertTrue(copy($origemFrontController, $frontController));
            self::assertNotFalse(file_put_contents(
                $autoloader,
                '<?php throw new RuntimeException("senha-super-secreta");'
            ));
            self::assertNotFalse(file_put_contents(
                $runner,
                <<<'PHP'
<?php
ob_start();
require __DIR__ . '/public/index.php';
$corpo = ob_get_clean();
echo 'HTTP_STATUS=' . http_response_code() . PHP_EOL . $corpo;
PHP
            ));

            $processo = proc_open(
                [PHP_BINARY, $runner],
                [
                    0 => ['pipe', 'r'],
                    1 => ['pipe', 'w'],
                    2 => ['pipe', 'w'],
                ],
                $pipes,
                $raizTemporaria
            );
            self::assertIsResource($processo);
            fclose($pipes[0]);
            $saida = stream_get_contents($pipes[1]);
            $erro = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $codigoSaida = proc_close($processo);

            self::assertSame(0, $codigoSaida);
            self::assertIsString($saida);
            self::assertStringContainsString('HTTP_STATUS=500', $saida);
            self::assertStringContainsString(
                'Ocorreu um erro inesperado. Tente novamente mais tarde.',
                $saida
            );
            self::assertStringNotContainsString('senha-super-secreta', $saida);
            self::assertIsString($erro);
            self::assertStringNotContainsString('senha-super-secreta', $erro);
        } finally {
            foreach ([$runner, $frontController, $autoloader] as $arquivo) {
                if (is_file($arquivo)) {
                    unlink($arquivo);
                }
            }
            rmdir($diretorioPublico);
            rmdir($diretorioVendor);
            rmdir($raizTemporaria);
        }
    }

    public function testMiddlewareNaoRegistradoFalhaFechado(): void
    {
        $router = new Router();
        $router->adicionar('GET', '/', [\App\Controllers\InicioController::class, 'index'], ['auth']);

        $response = $this->kernel($router)->tratar(new Request('GET', '/'));

        self::assertSame(500, $response->status);
    }

    private function kernel(Router $router, ?Container $container = null): Kernel
    {
        return new Kernel(
            $router,
            $container ?? new Container(),
            new View(dirname(__DIR__, 3) . '/app/Views'),
            $this->diretorioLogs
        );
    }

    /**
     * @param class-string<Throwable> $classe
     */
    private function excecao(string $classe): Throwable
    {
        return match ($classe) {
            AcessoNegadoException::class => new AcessoNegadoException('mensagem de teste'),
            ModuloInativoException::class => new ModuloInativoException('mensagem de teste'),
            ConflitoException::class => new ConflitoException('mensagem de teste'),
            CsrfException::class => new CsrfException('mensagem de teste'),
            NaoEncontradoException::class => new NaoEncontradoException('mensagem de teste'),
            MetodoNaoPermitidoException::class => new MetodoNaoPermitidoException(['GET']),
            default => new RuntimeException('exceção desconhecida'),
        };
    }

    private function tituloErro(int $status): string
    {
        return match ($status) {
            403 => 'Acesso negado',
            404 => 'Página não encontrada',
            405 => 'Método não permitido',
            409 => 'Conflito',
            419 => 'Solicitação expirada',
            default => '',
        };
    }
}

final class ControllerDoKernelTeste
{
    public static ?Throwable $excecao = null;

    public function mostrar(Request $request, int $id): Response
    {
        return Response::html('empresa:' . $id);
    }

    public function lancarExcecao(Request $request): Response
    {
        throw self::$excecao ?? new RuntimeException('erro controlado');
    }

    public function falhar(Request $request): Response
    {
        throw new RuntimeException('segredo interno');
    }
}
