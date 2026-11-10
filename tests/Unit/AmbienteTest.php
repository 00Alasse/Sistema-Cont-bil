<?php

declare(strict_types=1);

namespace Tests\Unit;

use Composer\Autoload\ClassLoader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Garante que o ambiente (XAMPP ou hospedagem) atende aos pré-requisitos
 * e que o autoload PSR-4 do namespace App\ aponta para a pasta app/.
 */
final class AmbienteTest extends TestCase
{
    public function testVersaoMinimaDoPhp(): void
    {
        self::assertTrue(
            version_compare(PHP_VERSION, '8.2.0', '>='),
            'O projeto requer PHP 8.2 ou superior. Versão atual: ' . PHP_VERSION
        );
    }

    #[DataProvider('extensoesObrigatorias')]
    public function testExtensaoObrigatoriaEstaHabilitada(string $extensao): void
    {
        self::assertTrue(
            extension_loaded($extensao),
            "A extensão '{$extensao}' não está habilitada. Verifique o php.ini."
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function extensoesObrigatorias(): array
    {
        return [
            'PDO' => ['pdo'],
            'driver MySQL/MariaDB' => ['pdo_mysql'],
            'strings multibyte (UTF-8)' => ['mbstring'],
            'detecção de tipo de arquivo' => ['fileinfo'],
        ];
    }

    public function testAutoloadDoNamespaceAppApontaParaPastaApp(): void
    {
        $prefixos = [];
        foreach (ClassLoader::getRegisteredLoaders() as $loader) {
            $prefixos += $loader->getPrefixesPsr4();
        }

        self::assertArrayHasKey('App\\', $prefixos);
        self::assertSame(
            realpath(dirname(__DIR__, 2) . '/app'),
            realpath($prefixos['App\\'][0])
        );
    }
}
