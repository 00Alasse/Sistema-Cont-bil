<?php

declare(strict_types=1);

use App\Controllers\Cadastro\EmpresaController;

return [
    'contratavel' => null,
    'menu' => [],
    'abas_empresa' => [
        ['rotulo' => 'Dados', 'url' => '/empresas/{id}', 'ordem' => 10],
    ],
    'cartoes_painel' => [],
    'rotas' => [
        ['GET', '/empresas', [EmpresaController::class, 'index'], ['auth', 'perfil:ADMIN,COLABORADOR']],
        ['GET', '/empresas/nova', [EmpresaController::class, 'nova'], ['auth', 'perfil:ADMIN,COLABORADOR']],
        ['POST', '/empresas', [EmpresaController::class, 'criar'], ['auth', 'perfil:ADMIN,COLABORADOR']],
        ['GET', '/empresas/{id}', [EmpresaController::class, 'mostrar'], ['auth', 'perfil:ADMIN,COLABORADOR']],
        ['GET', '/empresas/{id}/editar', [EmpresaController::class, 'editar'], ['auth', 'perfil:ADMIN,COLABORADOR']],
        ['POST', '/empresas/{id}', [EmpresaController::class, 'atualizar'], ['auth', 'perfil:ADMIN,COLABORADOR']],
        ['POST', '/empresas/{id}/excluir', [EmpresaController::class, 'excluir'], ['auth', 'perfil:ADMIN']],
    ],
];
