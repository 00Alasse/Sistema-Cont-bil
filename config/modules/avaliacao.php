<?php

declare(strict_types=1);

use App\Controllers\AvaliacaoController;

$equipe = ['auth', 'perfil:ADMIN,COLABORADOR'];
$todos = ['auth'];

return [
    'contratavel' => null,
    'menu' => [
        ['rotulo' => 'Avaliações', 'url' => '/avaliacoes', 'icone' => 'avaliacao',
            'perfis' => ['ADMIN', 'COLABORADOR'], 'ordem' => 45],
        ['rotulo' => 'Avaliar sistema', 'url' => '/avaliacao', 'icone' => 'avaliacao',
            'perfis' => ['CLIENTE'], 'ordem' => 50],
    ],
    'abas_empresa' => [],
    'cartoes_painel' => [],
    'rotas' => [
        ['GET', '/avaliacao', [AvaliacaoController::class, 'form'], $todos],
        ['POST', '/avaliacao', [AvaliacaoController::class, 'salvar'], $todos],
        ['GET', '/avaliacoes', [AvaliacaoController::class, 'index'], $equipe],
    ],
];
