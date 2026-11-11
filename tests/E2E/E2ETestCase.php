<?php

declare(strict_types=1);

namespace Tests\E2E;

use App\Core\Config;
use App\Core\Env;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\BancoDeTeste;
use Tests\Support\ClienteHttp;

/**
 * Sobe o servidor embutido do PHP (APP_ENV=testing, banco tributo_test) uma vez por classe
 * e o encerra no fim, mesmo quando os testes falham.
 */
abstract class E2ETestCase extends TestCase
{
    private const HOST = '127.0.0.1';
    private const PORTA = 8099;

    /** @var resource|null */
    private static $processo = null;

    private static ?string $arquivoLog = null;

    private static ?PDO $banco = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $raiz = dirname(__DIR__, 2);
        if (is_file($raiz . '/.env')) {
            Env::carregar($raiz . '/.env');
        }
        /** @var array<string, mixed> $config */
        $config = require $raiz . '/config/app.php';
        Config::definir($config);

        BancoDeTeste::preparar();
        self::carregarSeed($raiz);
        self::iniciarServidor($raiz);
        register_shutdown_function([self::class, 'encerrarServidor']);
    }

    public static function tearDownAfterClass(): void
    {
        self::encerrarServidor();
        self::$banco = null;

        parent::tearDownAfterClass();
    }

    protected function setUp(): void
    {
        parent::setUp();

        // O limite por IP é de 20 falhas: cada teste começa com o histórico limpo.
        $this->banco()->exec('DELETE FROM tentativas_login');
    }

    protected function cliente(): ClienteHttp
    {
        return new ClienteHttp('http://' . self::HOST . ':' . self::PORTA);
    }

    protected function banco(): PDO
    {
        return self::conexaoBanco();
    }

    private static function conexaoBanco(): PDO
    {
        if (self::$banco === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                (string) Config::obter('db.host'),
                (int) Config::obter('db.porta'),
                (string) Config::obter('db.banco_teste')
            );
            self::$banco = new PDO($dsn, (string) Config::obter('db.usuario'), (string) Config::obter('db.senha'), [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        }

        return self::$banco;
    }

    public static function encerrarServidor(): void
    {
        if (self::$processo !== null) {
            $estado = proc_get_status(self::$processo);
            if ($estado['running'] && PHP_OS_FAMILY === 'Windows') {
                // Garante o fim do servidor e de qualquer processo filho.
                exec('taskkill /F /T /PID ' . (int) $estado['pid'] . ' 2>NUL');
            }
            proc_terminate(self::$processo);
            proc_close(self::$processo);
            self::$processo = null;
        }

        if (self::$arquivoLog !== null && is_file(self::$arquivoLog)) {
            unlink(self::$arquivoLog);
        }
        self::$arquivoLog = null;
    }

    /**
     * Carrega os usuários fictícios dos seeds (senha de demonstração) se ainda não estiverem lá.
     */
    private static function carregarSeed(string $raiz): void
    {
        $pdo = self::conexaoBanco();
        $existe = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE email = 'admin@exemplo.test'")->fetchColumn();
        if ((int) $existe > 0) {
            return;
        }

        $sql = file_get_contents($raiz . '/database/seeds/01_nucleo.sql');
        if ($sql === false) {
            throw new RuntimeException('Não foi possível ler o seed do núcleo.');
        }
        foreach (preg_split('/;\s*\R/', $sql) ?: [] as $comando) {
            if (trim($comando) !== '') {
                $pdo->exec($comando);
            }
        }
    }

    private static function iniciarServidor(string $raiz): void
    {
        if (self::servidorResponde()) {
            throw new RuntimeException('A porta ' . self::PORTA . ' já está em uso; encerre o processo e repita.');
        }

        self::$arquivoLog = tempnam(sys_get_temp_dir(), 'tributo-servidor-') ?: null;
        $saida = self::$arquivoLog ?? (PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null');
        $ambiente = array_merge(getenv(), [
            'APP_ENV' => 'testing',
            'APP_DEBUG' => 'false',
            // Valor vazio some do ambiente no Windows; "/" equivale a sem subpasta.
            'APP_BASE_PATH' => '/',
        ]);

        $processo = proc_open(
            [PHP_BINARY, '-S', self::HOST . ':' . self::PORTA, '-t', 'public', 'public/index.php'],
            [0 => ['pipe', 'r'], 1 => ['file', $saida, 'a'], 2 => ['file', $saida, 'a']],
            $pipes,
            $raiz,
            $ambiente
        );
        if (!is_resource($processo)) {
            throw new RuntimeException('Não foi possível iniciar o servidor de testes.');
        }
        self::$processo = $processo;

        for ($i = 0; $i < 50; $i++) {
            if (self::servidorResponde()) {
                return;
            }
            usleep(100_000);
        }

        self::encerrarServidor();
        throw new RuntimeException('O servidor de testes não respondeu a tempo.');
    }

    /**
     * @phpstan-impure o resultado muda conforme o servidor sobe ou cai
     */
    private static function servidorResponde(): bool
    {
        $conexao = @fsockopen(self::HOST, self::PORTA);
        if ($conexao === false) {
            return false;
        }
        fclose($conexao);

        return true;
    }
}
