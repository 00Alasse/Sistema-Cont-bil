<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Nucleo\AuthService;

final class AuthMiddleware
{
    public const CHAVE_DESTINO_CAMINHO = 'auth_destino_caminho';
    public const CHAVE_DESTINO_QUERY = 'auth_destino_query';

    public function __construct(private readonly AuthService $auth)
    {
    }

    /**
     * @param callable(Request): Response $proximo
     */
    public function tratar(Request $request, callable $proximo): Response
    {
        $sessao = Session::atual();
        $usuario = $this->auth->usuarioDaSessao($sessao);

        if ($usuario === null) {
            $this->guardarDestino($sessao, $request);

            return Response::redirecionar(url('/login'));
        }

        return $proximo($request->comUsuario($usuario));
    }

    /**
     * Só páginas (GET) são retomadas depois do login. O destino sai da requisição já
     * normalizada pelo servidor (começa com "/"), então não há redirecionamento para outro site.
     */
    private function guardarDestino(Session $sessao, Request $request): void
    {
        if ($request->metodo() !== 'GET') {
            return;
        }

        $sessao->definir(self::CHAVE_DESTINO_CAMINHO, $request->caminho());
        $sessao->definir(self::CHAVE_DESTINO_QUERY, $request->todos());
    }
}
