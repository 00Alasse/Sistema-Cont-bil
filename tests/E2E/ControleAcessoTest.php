<?php

declare(strict_types=1);

namespace Tests\E2E;

use App\Controllers\Nucleo\TesteController;
use App\Core\Config;
use App\Core\Modulos;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\UsuarioLogado;
use App\Exceptions\NaoEncontradoException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ClienteHttp;

final class ControleAcessoTest extends E2ETestCase
{
    private const SENHA = 'Senha@123';

    public function testClienteEmRotaDeEscritorioRecebe403(): void
    {
        $cliente = $this->entrar('cliente.padaria@exemplo.test');

        $resposta = $cliente->get('/_teste/equipe');

        self::assertSame(403, $resposta->status);
        self::assertStringContainsString('Acesso negado', $resposta->corpo);
    }

    public function testColaboradorEmRotaSoDeAdminRecebe403(): void
    {
        $resposta = $this->entrar('ana@exemplo.test')->get('/_teste/admin');

        self::assertSame(403, $resposta->status);
    }

    public function testEscritorioSemOModuloRecebe403ModuloNaoContratado(): void
    {
        // Modelo Assessoria (escritório 2) não contratou o BPO nos seeds.
        $resposta = $this->entrar('admin@modelo.test')->get('/_teste/bpo');

        self::assertSame(403, $resposta->status);
        self::assertStringContainsString('Módulo não contratado', $resposta->corpo);
    }

    public function testUsuariosAutorizadosAcessamAsRotas(): void
    {
        $ana = $this->entrar('ana@exemplo.test');
        $admin = $this->entrar('admin@exemplo.test');

        self::assertSame(200, $ana->get('/_teste/equipe')->status);
        self::assertSame(200, $admin->get('/_teste/admin')->status);
        $bpo = $admin->get('/_teste/bpo');
        self::assertSame(200, $bpo->status);
        self::assertStringContainsString('Acesso liberado para Administradora Exemplo', $bpo->corpo);
    }

    public function testSemSessaoContinuaRedirecionandoParaOLogin(): void
    {
        $resposta = $this->cliente()->get('/_teste/admin');

        self::assertSame(302, $resposta->status);
        self::assertSame('/login', $resposta->location());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function ambientesForaDeTesting(): array
    {
        return [
            'production' => ['production'],
            'local' => ['local'],
            'vazio' => [''],
            'maiúsculas' => ['TESTING'],
        ];
    }

    #[DataProvider('ambientesForaDeTesting')]
    public function testRotasDeTesteNaoSaoRegistradasForaDeTesting(string $ambiente): void
    {
        $roteador = new Router();

        $this->comAmbiente($ambiente, static function () use ($roteador): void {
            (new Modulos($roteador, dirname(__DIR__, 2) . '/config/modules'))->carregar();
        });

        foreach (['/_teste/admin', '/_teste/equipe', '/_teste/bpo', '/_teste/protegida'] as $caminho) {
            try {
                $roteador->resolver('GET', $caminho);
                self::fail("A rota {$caminho} não deveria existir em APP_ENV={$ambiente}.");
            } catch (NaoEncontradoException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testRotasDeTesteSaoRegistradasEmTesting(): void
    {
        $roteador = new Router();

        $this->comAmbiente('testing', static function () use ($roteador): void {
            (new Modulos($roteador, dirname(__DIR__, 2) . '/config/modules'))->carregar();
        });

        self::assertSame(['auth', 'perfil:ADMIN'], $roteador->resolver('GET', '/_teste/admin')->middlewares);
        self::assertSame(['auth', 'modulo:BPO_FINANCEIRO'], $roteador->resolver('GET', '/_teste/bpo')->middlewares);
    }

    #[DataProvider('ambientesForaDeTesting')]
    public function testControllerDeTesteRecusaForaDeTestingMesmoComUsuarioAutorizado(string $ambiente): void
    {
        $usuario = new UsuarioLogado(1, 1, 'ADMIN', null, 'Administradora Exemplo', ['BPO_FINANCEIRO']);
        $requisicao = (new Request('GET', '/_teste/admin'))->comUsuario($usuario);

        $this->comAmbiente($ambiente, function () use ($requisicao): void {
            $this->expectException(NaoEncontradoException::class);
            (new TesteController())->acesso($requisicao);
        });
    }

    public function testControllerDeTesteResponderEmTesting(): void
    {
        $usuario = new UsuarioLogado(1, 1, 'ADMIN', null, 'Administradora Exemplo', []);
        $requisicao = (new Request('GET', '/_teste/admin'))->comUsuario($usuario);
        $resposta = null;

        $this->comAmbiente('testing', static function () use ($requisicao, &$resposta): void {
            $resposta = (new TesteController())->acesso($requisicao);
        });

        self::assertInstanceOf(Response::class, $resposta);
        self::assertSame(200, $resposta->status);
    }

    /**
     * Executa $acao com APP_ENV simulado e restaura a configuração real em seguida,
     * para não vazar o ambiente falso para os demais testes.
     *
     * @param callable(): void $acao
     */
    private function comAmbiente(string $ambiente, callable $acao): void
    {
        require_once dirname(__DIR__, 2) . '/app/Core/helpers.php';

        Config::definir(['app' => ['env' => $ambiente]]);
        try {
            $acao();
        } finally {
            /** @var array<string, mixed> $original */
            $original = require dirname(__DIR__, 2) . '/config/app.php';
            Config::definir($original);
        }
    }

    private function entrar(string $email): ClienteHttp
    {
        $cliente = $this->cliente();
        $token = ClienteHttp::extrairCsrf($cliente->get('/login')->corpo);
        self::assertNotNull($token);
        $resposta = $cliente->post('/login', ['_csrf' => $token, 'email' => $email, 'senha' => self::SENHA]);
        self::assertSame(302, $resposta->status);

        return $cliente;
    }
}
