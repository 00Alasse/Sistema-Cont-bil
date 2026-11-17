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
        return $this->view('inicio/index', ['titulo' => 'Início', 'redirecionarPara' => url('/login')], 200, 'auth');
    }
}