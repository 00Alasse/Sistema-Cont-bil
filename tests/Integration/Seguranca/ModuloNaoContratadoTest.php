<?php

declare(strict_types=1);

namespace Tests\Integration\Seguranca;

use App\Controllers\Nucleo\TesteController;
use App\Core\Modulos;
use App\Core\Router;
use RuntimeException;
use Tests\E2E\E2ETestCase;
use Tests\Support\ClienteHttp;
use Tests\Support\FabricaCadastro;

/**
 * Módulos contratáveis (ARCHITECTURE §4.1 e §7, item 6): rota de módulo que o escritório não
 * contratou, ou cuja contratação está inativa, responde 403 "Módulo não contratado". A lista de
 * módulos vem do banco (escritorio_modulos), nunca da requisição.
 */
final class ModuloNaoContratadoTest extends E2ETestCase
{
    private const SENHA = 'Senha@123';

    public function testTodaRotaComModuloRecusaEscritorioSemContratacao(): void
    {
        $rotas = self::rotasComModulo();
        // A rota de apoio /_teste/bpo garante pelo menos um caso enquanto não há módulo S pronto.
        self::assertArrayHasKey('GET /_teste/bpo', $rotas);

        $escritorio = (new FabricaCadastro($this->banco()))->criarEscritorio();
        $cliente = $this->entrar((new FabricaCadastro($this->banco()))->criarUsuario($escritorio, 'ADMIN'));

        foreach ($rotas as $descricao => [$metodo, $caminho]) {
            $resposta = $metodo === 'GET'
                ? $cliente->get($caminho)
                : $cliente->post($caminho, ['_csrf' => $this->token($cliente)]);

            self::assertSame(403, $resposta->status, $descricao);
            self::assertStringContainsString('Módulo não contratado', $resposta->corpo, $descricao);
        }
    }

    public function testRotasDosModulosContrataveisRecebemOMiddlewareDoModulo(): void
    {
        $roteador = new Router();
        foreach ((new Modulos($roteador))->carregar() as $nome => $modulo) {
            if ($modulo['contratavel'] === null) {
                continue;
            }
            foreach ((array) $modulo['rotas'] as $rota) {
                self::assertIsArray($rota);
                $middlewares = $roteador->resolver((string) $rota[0], self::exemplo((string) $rota[1]))->middlewares;
                self::assertContains('modulo:' . $modulo['contratavel'], $middlewares, $nome . ': ' . $rota[0] . ' ' . $rota[1]);
            }
        }

        // Enquanto nenhum módulo contratável existe, a regra do carregador é provada com um arquivo temporário.
        $this->assertCarregadorProtegeModuloContratavel();
    }

    public function testContratacaoSoValeQuandoAtivaEDoProprioEscritorio(): void
    {
        $fabrica = new FabricaCadastro($this->banco());
        $escritorio = $fabrica->criarEscritorio();
        $outro = $fabrica->criarEscritorio();
        $cliente = $this->entrar($fabrica->criarUsuario($escritorio, 'ADMIN'));

        // O outro escritório tem o BPO; isso não libera nada para este.
        $this->contratar($outro, 'BPO_FINANCEIRO', 1);
        self::assertSame(403, $cliente->get('/_teste/bpo')->status);

        // Outro módulo contratado não libera o BPO.
        $this->contratar($escritorio, 'CONSULTIVO', 1);
        self::assertSame(403, $cliente->get('/_teste/bpo')->status);

        // Registro de contratação inativo não conta.
        $this->contratar($escritorio, 'BPO_FINANCEIRO', 0);
        self::assertSame(403, $cliente->get('/_teste/bpo')->status);

        $this->contratar($escritorio, 'BPO_FINANCEIRO', 1);
        self::assertSame(200, $cliente->get('/_teste/bpo')->status);

        // A desativação vale já na próxima requisição da mesma sessão.
        $this->contratar($escritorio, 'BPO_FINANCEIRO', 0);
        $resposta = $cliente->get('/_teste/bpo');
        self::assertSame(403, $resposta->status);
        self::assertStringContainsString('Módulo não contratado', $resposta->corpo);
    }

    public function testModuloNaoPodeSerAtivadoPelaRequisicao(): void
    {
        $fabrica = new FabricaCadastro($this->banco());
        $escritorio = $fabrica->criarEscritorio();
        $cliente = $this->entrar($fabrica->criarUsuario($escritorio, 'ADMIN'));
        $forjado = http_build_query(['modulos' => ['BPO_FINANCEIRO'], 'modulo' => 'BPO_FINANCEIRO', 'bpo_ativo' => '1']);

        self::assertSame(403, $cliente->get('/_teste/bpo?' . $forjado)->status);
        $cliente->comCookie('modulos', 'BPO_FINANCEIRO');
        self::assertSame(403, $cliente->get('/_teste/bpo')->status);
    }

    /**
     * Cria um módulo contratável numa pasta temporária e confere que o carregador real
     * acrescenta "modulo:<CÓDIGO>" a todas as rotas dele, inclusive às que já têm middlewares.
     */
    private function assertCarregadorProtegeModuloContratavel(): void
    {
        $pasta = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tributo-q01-' . bin2hex(random_bytes(6));
        if (!mkdir($pasta, 0700) && !is_dir($pasta)) {
            throw new RuntimeException('Não foi possível criar a pasta temporária do teste.');
        }
        $arquivo = $pasta . DIRECTORY_SEPARATOR . 'consultivo.php';
        $handler = '[\\' . TesteController::class . '::class, \'acesso\']';
        file_put_contents($arquivo, "<?php\nreturn ['contratavel' => 'CONSULTIVO', 'menu' => [], 'abas_empresa' => [], "
            . "'cartoes_painel' => [], 'rotas' => [\n"
            . "    ['GET', '/q01-consultivo', {$handler}, []],\n"
            . "    ['POST', '/q01-consultivo/{id}', {$handler}, ['auth', 'perfil:ADMIN,COLABORADOR']],\n"
            . "]];\n");

        try {
            $roteador = new Router();
            (new Modulos($roteador, $pasta))->carregar();

            self::assertSame(['modulo:CONSULTIVO'], $roteador->resolver('GET', '/q01-consultivo')->middlewares);
            self::assertSame(
                ['auth', 'perfil:ADMIN,COLABORADOR', 'modulo:CONSULTIVO'],
                $roteador->resolver('POST', '/q01-consultivo/7')->middlewares
            );
        } finally {
            unlink($arquivo);
            rmdir($pasta);
        }
    }

    /**
     * Rotas registradas que exigem algum módulo, com um caminho de exemplo.
     *
     * @return array<string, array{string, string}>
     */
    private static function rotasComModulo(): array
    {
        $roteador = new Router();
        $rotas = [];
        foreach ((new Modulos($roteador))->carregar() as $modulo) {
            foreach ((array) $modulo['rotas'] as $rota) {
                if (!is_array($rota) || !is_string($rota[0] ?? null) || !is_string($rota[1] ?? null)) {
                    continue;
                }
                $caminho = self::exemplo($rota[1]);
                foreach ($roteador->resolver($rota[0], $caminho)->middlewares as $middleware) {
                    if (is_string($middleware) && str_starts_with($middleware, 'modulo:')) {
                        $rotas[strtoupper($rota[0]) . ' ' . $rota[1]] = [strtoupper($rota[0]), $caminho];
                    }
                }
            }
        }

        return $rotas;
    }

    /**
     * O middleware de módulo roda antes do controller: qualquer ID serve para o caminho.
     */
    private static function exemplo(string $padrao): string
    {
        return (string) preg_replace('/\{[A-Za-z_][A-Za-z0-9_]*\}/', '1', $padrao);
    }

    private function contratar(int $escritorioId, string $modulo, int $ativo): void
    {
        $this->banco()->prepare(
            'INSERT INTO escritorio_modulos (escritorio_id, modulo, ativo) VALUES (:escritorio, :modulo, :ativo)
             ON DUPLICATE KEY UPDATE ativo = VALUES(ativo)'
        )->execute(['escritorio' => $escritorioId, 'modulo' => $modulo, 'ativo' => $ativo]);
    }

    private function entrar(int $usuarioId): ClienteHttp
    {
        $stmt = $this->banco()->prepare('SELECT email FROM usuarios WHERE id = :id');
        $stmt->execute(['id' => $usuarioId]);
        $cliente = $this->cliente();
        $token = ClienteHttp::extrairCsrf($cliente->get('/login')->corpo);
        self::assertNotNull($token);
        $resposta = $cliente->post('/login', ['_csrf' => $token, 'email' => (string) $stmt->fetchColumn(), 'senha' => self::SENHA]);
        self::assertSame(302, $resposta->status);

        return $cliente;
    }

    private function token(ClienteHttp $cliente): string
    {
        $token = ClienteHttp::extrairCsrf($cliente->get('/_teste/protegida')->corpo);
        self::assertNotNull($token);

        return $token;
    }
}
