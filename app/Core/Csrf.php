<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Token CSRF por sessão. É estático porque as views o chamam por csrf_campo().
 */
final class Csrf
{
    private const CHAVE = '_csrf';

    public static function token(): string
    {
        $sessao = Session::atual();
        $token = $sessao->obter(self::CHAVE);

        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            $sessao->definir(self::CHAVE, $token);
        }

        return $token;
    }

    public static function validar(?string $token): bool
    {
        if ($token === null || $token === '') {
            return false;
        }

        $esperado = Session::atual()->obter(self::CHAVE);

        // hash_equals compara em tempo constante, sem revelar quantos caracteres batem.
        return is_string($esperado) && $esperado !== '' && hash_equals($esperado, $token);
    }
}
