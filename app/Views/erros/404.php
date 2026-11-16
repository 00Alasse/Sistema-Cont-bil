<?php

declare(strict_types=1);

$erroTitulo = is_string($titulo ?? null) ? $titulo : 'Página não encontrada';
$erroMensagem = is_string($mensagem ?? null) ? $mensagem : 'O recurso solicitado não foi encontrado.';
$erroIcone = 'buscar';
$erroTom = 'aviso';
require dirname(__DIR__) . '/partials/pagina-erro.php';