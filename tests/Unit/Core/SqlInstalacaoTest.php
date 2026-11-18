<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * database/tributo_instalacao.sql (gerado por `php bin/db.php build`): cria o banco, as tabelas e os dados
 * de exemplo. Só lê arquivos e gera o SQL em memória; não conecta em banco nenhum.
 */
final class SqlInstalacaoTest extends TestCase
{
    private const RAIZ = __DIR__ . '/../../..';

    public static function setUpBeforeClass(): void
    {
        require_once self::RAIZ . '/bin/db.php';
    }

    public function testScriptDeInstalacaoEstaSincronizadoComSchemaESeeds(): void
    {
        $arquivo = file_get_contents(self::RAIZ . '/database/tributo_instalacao.sql');
        self::assertNotFalse($arquivo, 'rode `php bin/db.php build`');

        $esperado = gerarSqlInstalacao(
            gerarSqlConsolidado(
                arquivosSql(self::RAIZ . '/database/schema'),
                arquivosSql(self::RAIZ . '/database/seeds')
            ),
            destinoBanco(false)
        );

        self::assertSame($this->semData($esperado), $this->semData($arquivo));
    }

    public function testCriaOBancoAntesDasTabelasEDosDados(): void
    {
        $sql = (string) file_get_contents(self::RAIZ . '/database/tributo_instalacao.sql');

        $criaBanco = strpos($sql, 'CREATE DATABASE IF NOT EXISTS `tributo` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;');
        $usa = strpos($sql, 'USE `tributo`;');
        $tabela = strpos($sql, 'CREATE TABLE escritorios');
        $dados = strpos($sql, 'INSERT INTO escritorios');

        self::assertNotFalse($criaBanco);
        self::assertNotFalse($usa);
        self::assertNotFalse($tabela);
        self::assertNotFalse($dados);
        self::assertTrue($criaBanco < $usa && $usa < $tabela && $tabela < $dados);
        foreach (['obrigacoes', 'solicitacoes_documentos', 'avaliacoes'] as $tabelaNegocio) {
            self::assertStringContainsString('CREATE TABLE ' . $tabelaNegocio, $sql);
        }
    }

    public function testTributoSqlContinuaSemCreateDatabase(): void
    {
        // ADR: o tributo.sql é importado em hospedagem compartilhada, onde o banco vem do painel.
        $consolidado = (string) file_get_contents(self::RAIZ . '/database/tributo.sql');

        self::assertStringNotContainsString('CREATE DATABASE', strtoupper($consolidado));
        self::assertStringNotContainsString('USE `', $consolidado);
    }

    public function testRecusaNomeDeBancoInvalido(): void
    {
        $this->expectException(RuntimeException::class);

        gerarSqlInstalacao('SELECT 1;', 'tributo`; DROP DATABASE x; --');
    }

    private function semData(string $sql): string
    {
        $normalizado = str_replace("\r\n", "\n", $sql);

        return preg_replace('/^(-- Gerado em: )\d{4}-\d{2}-\d{2}$/m', '$1<DATA>', $normalizado) ?? $normalizado;
    }
}
