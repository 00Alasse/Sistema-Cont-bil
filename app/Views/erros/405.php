<?php

declare(strict_types=1);

$erroTitulo = is_string($titulo ?? null) ? $titulo : 'Método não permitido';
$erroMensagem = is_string($mensagem ?? null) ? $mensagem : 'O método usado não é permitido para esta página.';
$erroIcone = 'aviso';
$erroTom = 'aviso';
$erroDetalhe = isset($metodosPermitidos) && is_array($metodosPermitidos) && $metodosPermitidos !== []
    ? 'Métodos permitidos: ' . implode(', ', array_map('strval', $metodosPermitidos))
    : '';
require dirname(__DIR__) . '/partials/pagina-erro.php';