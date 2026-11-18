<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;

/**
 * Página inicial pública (ADR-024): apresenta o produto e leva ao login. Quem já está autenticado
 * e clica em "Entrar" é encaminhado pelo GuestMiddleware ao seu painel (/painel ou /portal).
 */
final class InicioController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->view('inicio/index', ['titulo' => 'Início'], 200, 'auth');
    }
}
