<?php

declare(strict_types=1);

namespace App\Controllers\Nucleo;

use App\Core\Config;
use App\Core\Request;
use App\Core\Response;
use App\Exceptions\NaoEncontradoException;

/**
 * Handlers das rotas de apoio aos testes E2E. As rotas só são registradas com APP_ENV=testing
 * (config/modules/nucleo.php); a checagem aqui é uma segunda trava contra registro indevido.
 */
final class TesteController
{
    public function acesso(Request $request): Response
    {
        if (Config::obter('app.env') !== 'testing') {
            throw new NaoEncontradoException();
        }

        return Response::html('Acesso liberado para ' . e($request->usuario()->nome));
    }
}
