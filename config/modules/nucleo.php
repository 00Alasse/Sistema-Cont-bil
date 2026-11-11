<?php

declare(strict_types=1);

use App\Controllers\InicioController;
use App\Controllers\Nucleo\AuthController;
use App\Core\Config;

$rotas = [
    ['GET', '/', [InicioController::class, 'index'], []],
    ['GET', '/login', [AuthController::class, 'mostrarLogin'], ['guest']],
    ['POST', '/login', [AuthController::class, 'entrar'], ['guest']],
    ['POST', '/logout', [AuthController::class, 'sair'], ['auth']],
];

// Rota de apoio aos testes E2E (acesso sem sessão e retorno ao destino); inexistente fora de testing.
if (Config::obter('app.env') === 'testing') {
    $rotas[] = ['GET', '/_teste/protegida', [AuthController::class, 'paginaProtegidaDeTeste'], ['auth']];
}

return [
    'contratavel' => null,
    'menu' => [],
    'abas_empresa' => [],
    'cartoes_painel' => [],
    'rotas' => $rotas,
];
