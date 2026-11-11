<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Nucleo\AuthService;

final class GuestMiddleware
{
    public function __construct(private readonly AuthService $auth)
    {
    }

    /**
     * @param callable(Request): Response $proximo
     */
    public function tratar(Request $request, callable $proximo): Response
    {
        // Usa a mesma checagem do AuthMiddleware: uma sessão de usuário já desativado
        // não conta como autenticada, evitando laço entre /login e a área restrita.
        $usuario = $this->auth->usuarioDaSessao(Session::atual());
        if ($usuario !== null) {
            return Response::redirecionar(url($this->auth->destinoInicial($usuario)));
        }

        return $proximo($request);
    }
}
