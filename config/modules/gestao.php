<?php

declare(strict_types=1);

use App\Controllers\Gestao\PainelController;

// Gestão faz parte do Plano Base: sem código contratável, logo sem middleware "modulo:".
return [
    'contratavel' => null,
    'menu' => [
        ['rotulo' => 'Painel', 'url' => '/painel', 'icone' => 'painel',
            'perfis' => ['ADMIN', 'COLABORADOR'], 'ordem' => 1],
    ],
    'abas_empresa' => [],
    'cartoes_painel' => [],
    'rotas' => [
        // Substitui a rota provisória que existia em nucleo.php (F10).
        ['GET', '/painel', [PainelController::class, 'index'], ['auth', 'perfil:ADMIN,COLABORADOR']],
    ],
];
