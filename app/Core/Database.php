<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use Throwable;

final class Database
{
    private static ?PDO $conexao = null;

    public static function conexao(): PDO
    {
        if (self::$conexao !== null) {
            return self::$conexao;
        }

        $host = (string) Config::obter('db.host');
        $porta = (int) Config::obter('db.porta');
        $banco = (string) Config::obter('db.banco');
        $charset = (string) Config::obter('db.charset');
        $dsn = "mysql:host={$host};port={$porta};dbname={$banco};charset={$charset}";

        $conexao = new PDO(
            $dsn,
            (string) Config::obter('db.usuario'),
            (string) Config::obter('db.senha'),
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
        $conexao->exec('SET NAMES utf8mb4');
        $conexao->exec("SET time_zone = '-03:00'");

        self::$conexao = $conexao;

        return self::$conexao;
    }

    public static function transacao(callable $fn): mixed
    {
        $conexao = self::conexao();
        if ($conexao->inTransaction()) {
            return $fn();
        }

        $conexao->beginTransaction();
        try {
            $resultado = $fn();
            $conexao->commit();

            return $resultado;
        } catch (Throwable $excecao) {
            $conexao->rollBack();
            throw $excecao;
        }
    }
}
