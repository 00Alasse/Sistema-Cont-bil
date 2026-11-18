<?php

declare(strict_types=1);

$erroTitulo = is_string($titulo ?? null) ? $titulo : 'Erro interno';
$erroMensagem = is_string($mensagem ?? null) ? $mensagem : 'Ocorreu um erro inesperado. Tente novamente mais tarde.';
$erroIcone = 'erro';
$erroTom = 'perigo';
require dirname(__DIR__) . '/partials/pagina-erro.php';