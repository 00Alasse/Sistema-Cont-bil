<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class Env
{
    public static function carregar(string $arquivo): void
    {
        if (!is_file($arquivo)) {
            throw new RuntimeException('Crie o arquivo .env a partir de .env.example');
        }

        $linhas = file($arquivo, FILE_IGNORE_NEW_LINES);
        if ($linhas === false) {
            throw new RuntimeException('Não foi possível ler o arquivo de ambiente.');
        }

        foreach ($linhas as $linha) {
            self::carregarLinha($linha);
        }
    }

    public static function obter(string $chave, ?string $padrao = null): ?string
    {
        $valor = getenv($chave);
        if ($valor !== false) {
            return $valor;
        }

        if (isset($_ENV[$chave])) {
            return (string) $_ENV[$chave];
        }

        if (isset($_SERVER[$chave])) {
            return (string) $_SERVER[$chave];
        }

        return $padrao;
    }

    private static function carregarLinha(string $linha): void
    {
        $linha = trim($linha);
        if ($linha === '' || str_starts_with($linha, '#') || !str_contains($linha, '=')) {
            return;
        }

        [$chave, $valor] = explode('=', $linha, 2);
        $chave = trim($chave);
        if ($chave === '' || self::obter($chave) !== null) {
            return;
        }

        $valor = trim($valor);
        if (strlen($valor) >= 2) {
            $aspas = $valor[0];
            if (($aspas === '"' || $aspas === "'") && $valor[strlen($valor) - 1] === $aspas) {
                $valor = substr($valor, 1, -1);
            }
        }

        putenv($chave . '=' . $valor);
        $_ENV[$chave] = $valor;
    }
}
