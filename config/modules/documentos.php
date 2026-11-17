<?php

declare(strict_types=1);

use App\Controllers\Documento\PortalController;
use App\Controllers\Documento\SolicitacaoController;

$equipe = ['auth', 'perfil:ADMIN,COLABORADOR'];
$cliente = ['auth', 'perfil:CLIENTE'];

return [
    'contratavel' => null,
    'menu' => [
        ['rotulo' => 'Documentos', 'url' => '/solicitacoes', 'icone' => 'documentos',
            'perfis' => ['ADMIN', 'COLABORADOR'], 'ordem' => 25],
        ['rotulo' => 'Portal do cliente', 'url' => '/portal', 'icone' => 'portal',
            'perfis' => ['CLIENTE'], 'ordem' => 10],
    ],
    'abas_empresa' => [],
    'cartoes_painel' => [
        \App\Services\Documento\CartaoDocumentosPainel::class,
    ],
    'rotas' => [
        // Rotas do Escritório (Contador / Colaborador)
        ['GET', '/solicitacoes', [SolicitacaoController::class, 'index'], $equipe],
        ['GET', '/solicitacoes/nova', [SolicitacaoController::class, 'nova'], $equipe],
        ['POST', '/solicitacoes', [SolicitacaoController::class, 'criar'], $equipe],
        ['POST', '/solicitacoes/{id}/aceitar', [SolicitacaoController::class, 'aceitar'], $equipe],
        ['POST', '/solicitacoes/{id}/recusar', [SolicitacaoController::class, 'recusar'], $equipe],
        ['GET', '/solicitacoes/{id}/download', [SolicitacaoController::class, 'download'], $equipe],

        // Rotas do Portal do Cliente (Empresário)
        ['GET', '/portal', [PortalController::class, 'index'], $cliente],
        ['POST', '/portal/documentos/{id}/enviar', [PortalController::class, 'enviar'], $cliente],
        ['GET', '/portal/documentos/{id}/download', [PortalController::class, 'download'], $cliente],
    ],
];
