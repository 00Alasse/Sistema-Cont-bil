<?php

declare(strict_types=1);

use App\Controllers\InicioController;
use App\Controllers\Nucleo\AuthController;
use App\Controllers\Nucleo\LayoutTesteController;
use App\Controllers\Nucleo\TesteController;
use App\Core\Config;

$rotas = [
    ['GET', '/', [InicioController::class, 'index'], []],
    ['GET', '/painel', [LayoutTesteController::class, 'escritorio'], ['auth', 'perfil:ADMIN,COLABORADOR']],
    ['GET', '/login', [AuthController::class, 'mostrarLogin'], ['guest']],
    ['POST', '/login', [AuthController::class, 'entrar'], ['guest']],
    ['POST', '/logout', [AuthController::class, 'sair'], ['auth']],
];

// Rota de apoio aos testes E2E (acesso sem sessão e retorno ao destino); inexistente fora de testing.
if (Config::obter('app.env') === 'testing') {
    $rotas[] = ['GET', '/_teste/protegida', [AuthController::class, 'paginaProtegidaDeTeste'], ['auth']];
    $rotas[] = ['GET', '/_teste/admin', [TesteController::class, 'acesso'], ['auth', 'perfil:ADMIN']];
    $rotas[] = ['GET', '/_teste/equipe', [TesteController::class, 'acesso'], ['auth', 'perfil:ADMIN,COLABORADOR']];
    $rotas[] = ['GET', '/_teste/bpo', [TesteController::class, 'acesso'], ['auth', 'modulo:BPO_FINANCEIRO']];
    $rotas[] = ['GET', '/_teste/layout-escritorio', [LayoutTesteController::class, 'escritorioTeste'], ['auth', 'perfil:ADMIN,COLABORADOR']];
    $rotas[] = ['GET', '/_teste/layout-portal', [LayoutTesteController::class, 'portalTeste'], ['auth', 'perfil:CLIENTE']];
}

return [
    'contratavel' => null,
    'menu' => [
        ['rotulo' => 'Painel', 'url' => '/painel', 'icone' => 'painel',
            'perfis' => ['ADMIN', 'COLABORADOR'], 'ordem' => 10],
        ['rotulo' => 'Empresas', 'url' => '/empresas', 'icone' => 'empresa',
            'perfis' => ['ADMIN', 'COLABORADOR'], 'ordem' => 20],
        ['rotulo' => 'Obrigações', 'url' => '/obrigacoes', 'icone' => 'obrigacoes',
            'perfis' => ['ADMIN', 'COLABORADOR'], 'ordem' => 30],
        ['rotulo' => 'Usuários', 'url' => '/usuarios', 'icone' => 'usuarios',
            'perfis' => ['ADMIN'], 'ordem' => 40],
        ['rotulo' => 'Portal do cliente', 'url' => '/portal', 'icone' => 'portal',
            'perfis' => ['CLIENTE'], 'ordem' => 10],
    ],
    'abas_empresa' => [],
    'cartoes_painel' => [],
    'rotas' => $rotas,
];
