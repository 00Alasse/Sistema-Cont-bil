<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

final class SessaoMiddleware
{
    /**
     * @param callable(Request): Response $proximo
     */
    public function tratar(Request $request, callable $proximo): Response
    {
        $sessao = Session::daConfiguracao();
        $sessao->iniciar();
        Session::definirAtual($sessao);

        return $proximo($request);
    }
}
