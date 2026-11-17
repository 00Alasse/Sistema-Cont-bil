<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;

final class InicioController extends Controller
{
    public function index(Request $request): Response
    {
        $loginUrl = e(url('/login'));

        return Response::html(
            '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<meta http-equiv="refresh" content="0; url=' . $loginUrl . '">'
            . '<title>-Tributo!</title>'
            . '<link rel="stylesheet" href="' . e(url('/assets/css/app.css')) . '">'
            . '</head><body class="corpo-auth">'
            . '<main class="pagina-auth"><div class="cartao cartao-auth" style="text-align: center;">'
            . '<h1 class="login-marca">-Tributo!</h1>'
            . '<p class="login-subtitulo">Plataforma de Gestão Contábil e Fiscal</p>'
            . '<p style="margin: 2rem 0;"><a class="botao botao-bloco" href="' . $loginUrl . '">Acessar a Plataforma</a></p>'
            . '</div></main>'
            . '</body></html>'
        );
    }
}
