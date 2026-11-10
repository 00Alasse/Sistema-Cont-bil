<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Core\Config;
use App\Core\Database;
use App\Core\Env;
use PDO;
use PDOException;
use RuntimeException;
use Tests\Integration\IntegrationTestCase;

final class DatabaseTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        $arquivoEnv = dirname(__DIR__, 3) . '/.env';
        if (is_file($arquivoEnv)) {
            Env::carregar($arquivoEnv);
        }

        /** @var array<string, mixed> $config */
        $config = require dirname(__DIR__, 3) . '/config/app.php';
        Config::definir($config);

        parent::setUp();
    }

    protected function iniciarTransacaoExterna(): bool
    {
        return false;
    }

    public function testConectaAoBancoDeTeste(): void
    {
        $conexao = $this->conexaoDisponivel();

        self::assertInstanceOf(PDO::class, $conexao);
        self::assertSame('tributo_test', $conexao->query('SELECT DATABASE()')->fetchColumn());
    }

    public function testReverteTransacaoQuandoOcorreUmaExcecao(): void
    {
        $conexao = $this->conexaoDisponivel();
        $conexao->exec(
            'CREATE TEMPORARY TABLE f01_transacao_teste (id INT NOT NULL PRIMARY KEY) ENGINE=InnoDB'
        );

        try {
            try {
                Database::transacao(static function () use ($conexao): void {
                    $conexao->exec('INSERT INTO f01_transacao_teste (id) VALUES (1)');
                    throw new RuntimeException('forçar rollback');
                });
                self::fail('A exceção da transação deveria ser relançada.');
            } catch (RuntimeException $excecao) {
                self::assertSame('forçar rollback', $excecao->getMessage());
            }

            self::assertSame(
                0,
                (int) $conexao->query('SELECT COUNT(*) FROM f01_transacao_teste')->fetchColumn()
            );
        } finally {
            $conexao->exec('DROP TEMPORARY TABLE IF EXISTS f01_transacao_teste');
        }
    }

    public function testTransacaoAninhadaNaoConfirmaATransacaoExterna(): void
    {
        $conexao = $this->conexaoDisponivel();
        $conexao->beginTransaction();

        try {
            self::assertSame('executado', Database::transacao(
                static fn (): string => 'executado'
            ));
            self::assertTrue($conexao->inTransaction());
        } finally {
            if ($conexao->inTransaction()) {
                $conexao->rollBack();
            }
        }
    }

    private function conexaoDisponivel(): PDO
    {
        try {
            return Database::conexao();
        } catch (PDOException $excecao) {
            $codigoDriver = $excecao->errorInfo[1] ?? null;
            if (in_array($codigoDriver, [2002, 2003], true)) {
                self::markTestSkipped('Inicie o MySQL no XAMPP para executar este teste.');
            }

            throw $excecao;
        }
    }
}
