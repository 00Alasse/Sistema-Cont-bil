<?php

declare(strict_types=1);

use App\Controllers\InicioController;

return [
    'contratavel' => null,
    'menu' => [],
    'abas_empresa' => [],
    'cartoes_painel' => [],
    'rotas' => [
        ['GET', '/', [InicioController::class, 'index'], []],
    ],
];
