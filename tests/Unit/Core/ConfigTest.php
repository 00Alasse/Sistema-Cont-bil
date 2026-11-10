<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Config;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    /**
     * @var array<string, string|false>
     */
    private array $ambienteOriginal = [];

    protected function tearDown(): void
    {
        foreach ($this->ambienteOriginal as $chave => $valor) {
            if ($valor === false) {
                putenv($chave);
                unset($_ENV[$chave], $_SERVER[$chave]);
                continue;
            }

            putenv($chave . '=' . $valor);
        }

        Config::definir([]);
        parent::tearDown();
    }

    public function testObtemValorUsandoNotacaoDePonto(): void
    {
        Config::definir([
            'app' => ['nome' => '-Tributo!'],
            'db' => ['host' => '127.0.0.1'],
        ]);

        self::assertSame('-Tributo!', Config::obter('app.nome'));
        self::assertSame('127.0.0.1', Config::obter('db.host'));
    }

    public function testUsaValorPadraoQuandoChaveNaoExiste(): void
    {
        Config::definir(['app' => []]);

        self::assertSame('padrão', Config::obter('app.chave_inexistente', 'padrão'));
    }

    public function testUsaBancoDeTesteQuandoAmbienteEhTesting(): void
    {
        $this->definirVariavel('APP_ENV', 'testing');
        $this->definirVariavel('DB_DATABASE', 'tributo');
        $this->definirVariavel('DB_TEST_DATABASE', 'tributo_test_f01');

        /** @var array<string, mixed> $config */
        $config = require dirname(__DIR__, 3) . '/config/app.php';
        Config::definir($config);

        self::assertSame('testing', Config::obter('app.env'));
        self::assertSame('tributo_test_f01', Config::obter('db.banco'));
    }

    private function definirVariavel(string $chave, string $valor): void
    {
        if (!array_key_exists($chave, $this->ambienteOriginal)) {
            $atual = getenv($chave);
            $this->ambienteOriginal[$chave] = $atual;
        }

        putenv($chave . '=' . $valor);
    }
}
