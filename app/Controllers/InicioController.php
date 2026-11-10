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
        return Response::html(
            '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>-Tributo!</title></head><body><h1>-Tributo!</h1>'
            . '<p>Estrutura inicial instalada. As funcionalidades estão em desenvolvimento.</p>'
            . '</body></html>'
        );
    }
}
