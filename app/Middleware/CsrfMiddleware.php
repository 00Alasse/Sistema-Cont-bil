<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Exceptions\CsrfException;

final class CsrfMiddleware
{
    private const METODOS_SEGUROS = ['GET', 'HEAD', 'OPTIONS'];

    /**
     * @param callable(Request): Response $proximo
     */
    public function tratar(Request $request, callable $proximo): Response
    {
        // Qualquer método que altera estado exige token; hoje só há rotas POST.
        if (!in_array($request->metodo(), self::METODOS_SEGUROS, true)) {
            $token = $request->input('_csrf');

            if (!is_string($token) || !Csrf::validar($token)) {
                throw new CsrfException();
            }
        }

        return $proximo($request);
    }
}
