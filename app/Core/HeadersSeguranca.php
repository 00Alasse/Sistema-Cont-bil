<?php

declare(strict_types=1);

namespace App\Core;

final class HeadersSeguranca
{
    private const HEADERS = [
        'X-Frame-Options' => 'DENY',
        'X-Content-Type-Options' => 'nosniff',
        'Referrer-Policy' => 'same-origin',
        'Content-Security-Policy' => "default-src 'self'; script-src 'self'; style-src 'self'; "
            . "img-src 'self' data:; frame-ancestors 'none'; form-action 'self'",
    ];

    public static function aplicar(Response $resposta): Response
    {
        foreach (self::HEADERS as $nome => $valor) {
            $resposta = $resposta->comHeader($nome, $valor);
        }

        return $resposta;
    }
}
