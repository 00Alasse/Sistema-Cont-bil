<?php

declare(strict_types=1);

use App\Controllers\Fiscal\EmpresaTiposController;
use App\Controllers\Fiscal\ObrigacaoController;
use App\Controllers\Fiscal\TipoObrigacaoController;

// Fiscal faz parte do Plano Base: sem código contratável, logo sem middleware "modulo:".
$equipe = ['auth', 'perfil:ADMIN,COLABORADOR'];
$admin = ['auth', 'perfil:ADMIN'];

return [
    'contratavel' => null,
    'menu' => [
        ['rotulo' => 'Tipos de obrigação', 'url' => '/tipos-obrigacao', 'icone' => 'tipos',
            'perfis' => ['ADMIN', 'COLABORADOR'], 'ordem' => 35],
    ],
    'abas_empresa' => [
        ['rotulo' => 'Obrigações', 'url' => '/empresas/{id}/tipos-obrigacao', 'ordem' => 20],
    ],
    'cartoes_painel' => [],
    'rotas' => [
        ['GET', '/tipos-obrigacao', [TipoObrigacaoController::class, 'index'], $equipe],
        ['GET', '/tipos-obrigacao/novo', [TipoObrigacaoController::class, 'novo'], $admin],
        ['POST', '/tipos-obrigacao', [TipoObrigacaoController::class, 'criar'], $admin],
        ['GET', '/tipos-obrigacao/{id}/editar', [TipoObrigacaoController::class, 'editar'], $admin],
        ['POST', '/tipos-obrigacao/{id}', [TipoObrigacaoController::class, 'atualizar'], $admin],
        ['POST', '/tipos-obrigacao/{id}/excluir', [TipoObrigacaoController::class, 'excluir'], $admin],
        ['GET', '/empresas/{id}/tipos-obrigacao', [EmpresaTiposController::class, 'mostrar'], $equipe],
        ['POST', '/empresas/{id}/tipos-obrigacao', [EmpresaTiposController::class, 'salvar'], $equipe],
        // O item de menu "Obrigações" já é declarado em nucleo.php.
        ['GET', '/obrigacoes', [ObrigacaoController::class, 'index'], $equipe],
        ['GET', '/obrigacoes/nova', [ObrigacaoController::class, 'nova'], $equipe],
        ['POST', '/obrigacoes', [ObrigacaoController::class, 'criar'], $equipe],
        ['GET', '/obrigacoes/{id}', [ObrigacaoController::class, 'mostrar'], $equipe],
        ['GET', '/obrigacoes/{id}/editar', [ObrigacaoController::class, 'editar'], $equipe],
        ['POST', '/obrigacoes/{id}', [ObrigacaoController::class, 'atualizar'], $equipe],
        ['POST', '/obrigacoes/{id}/excluir', [ObrigacaoController::class, 'excluir'], $admin],
        // Reabrir é só para ADMIN, mas essa regra depende da transição: fica no ObrigacaoStatusService.
        ['POST', '/obrigacoes/{id}/status', [ObrigacaoController::class, 'status'], $equipe],
    ],
];
