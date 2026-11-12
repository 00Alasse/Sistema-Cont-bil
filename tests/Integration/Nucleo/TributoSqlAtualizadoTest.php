<?php

declare(strict_types=1);

namespace Tests\Integration\Nucleo;

use Tests\Integration\IntegrationTestCase;

final class TributoSqlAtualizadoTest extends IntegrationTestCase
{
    public function testSqlConsolidadoEstaSincronizado(): void
    {
        $raiz = dirname(__DIR__, 3);
        require_once $raiz . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'db.php';
        $consolidado = file_get_contents(
            $raiz . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'tributo.sql'
        );
        $geradoEmMemoria = gerarSqlConsolidado(
            arquivosSql($raiz . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'schema'),
            arquivosSql($raiz . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'seeds')
        );

        self::assertNotFalse($consolidado);
        self::assertStringContainsString('CREATE TABLE escritorios', $consolidado);
        self::assertStringContainsString('INSERT INTO escritorios', $consolidado);
        self::assertStringNotContainsString('CREATE DATABASE', strtoupper($consolidado));
        self::assertStringContainsString('CREATE TABLE obrigacoes', $consolidado);
        self::assertSame($this->semDataDoCabecalho($geradoEmMemoria), $this->semDataDoCabecalho($consolidado));
    }

    public function testComandoBuildAtualizaSqlConsolidadoSemCriarBanco(): void
    {
        $raiz = dirname(__DIR__, 3);
        $arquivo = $raiz . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'tributo.sql';
        $original = file_get_contents($arquivo);
        self::assertNotFalse($original);

        try {
            $resultado = $this->executarComando(['build']);
            self::assertSame(0, $resultado['codigo'], $resultado['saida']);

            $gerado = file_get_contents($arquivo);
            self::assertNotFalse($gerado);
            self::assertStringNotContainsString('CREATE DATABASE', strtoupper($gerado));
            self::assertStringContainsString('SET FOREIGN_KEY_CHECKS=0;', $gerado);
            self::assertStringContainsString('SET FOREIGN_KEY_CHECKS=1;', $gerado);
        } finally {
            self::assertNotFalse(file_put_contents($arquivo, $original, LOCK_EX));
        }
    }

    public function testBuildNaoAceitaOpcoesDeRebuild(): void
    {
        $resultado = $this->executarComando(['build', '--test']);

        self::assertNotSame(0, $resultado['codigo']);
        self::assertStringContainsString('Uso:', $resultado['saida']);
    }

    public function testRebuildRejeitaDestinosArbitrariosAntesDeConectar(): void
    {
        $resultado = $this->executarComando(['rebuild', '--database=producao']);

        self::assertNotSame(0, $resultado['codigo']);
        self::assertStringContainsString('Uso:', $resultado['saida']);
        self::assertStringNotContainsString('CREATE DATABASE', strtoupper($resultado['saida']));
    }

    public function testDestinosDoRebuildSaoFixosESeparados(): void
    {
        $raiz = dirname(__DIR__, 3);
        require_once $raiz . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'db.php';

        self::assertSame('tributo', destinoBanco(false));
        self::assertSame('tributo_test', destinoBanco(true));
    }

    /**
     * @param list<string> $argumentos
     * @return array{codigo: int, saida: string}
     */
    private function executarComando(array $argumentos): array
    {
        $raiz = dirname(__DIR__, 3);
        $processo = proc_open(
            array_merge([
                PHP_BINARY,
                $raiz . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'db.php',
            ], $argumentos),
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            $raiz
        );
        self::assertIsResource($processo);

        fclose($pipes[0]);
        $saida = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return ['codigo' => proc_close($processo), 'saida' => (string) $saida];
    }

    private function semDataDoCabecalho(string $sql): string
    {
        return preg_replace('/^(-- Gerado em: )\d{4}-\d{2}-\d{2}$/m', '$1<DATA>', $sql) ?? $sql;
    }
}
