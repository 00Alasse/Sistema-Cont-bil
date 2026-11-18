<?php

declare(strict_types=1);

$erroTitulo = is_string($titulo ?? null) ? $titulo : 'Acesso negado';
$erroMensagem = is_string($mensagem ?? null) ? $mensagem : 'Você não tem permissão para acessar esta página.';
$erroIcone = 'aviso';
$erroTom = 'aviso';
require dirname(__DIR__) . '/partials/pagina-erro.php';