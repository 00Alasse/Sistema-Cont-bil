<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Database;
use App\Core\Config;
use App\Core\Env;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\BancoDeTeste;

abstract class IntegrationTestCase extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $raiz = dirname(__DIR__, 2);
        $arquivoEnv = $raiz . DIRECTORY_SEPARATOR . '.env';
        if (is_file($arquivoEnv)) {
            Env::carregar($arquivoEnv);
        }

        /** @var array<string, mixed> $config */
        $config = require $raiz . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'app.php';
        Config::definir($config);
        BancoDeTeste::preparar();
    }

    protected function setUp(): void
    {
        if ($this->iniciarTransacaoExterna()) {
            Database::conexao()->beginTransaction();
        }

        parent::setUp();
    }

    protected function tearDown(): void
    {
        $conexao = Database::conexao();
        if ($conexao->inTransaction()) {
            $conexao->rollBack();
        }

        parent::tearDown();
    }

    protected function conexao(): PDO
    {
        return Database::conexao();
    }

    protected function iniciarTransacaoExterna(): bool
    {
        return true;
    }
}
