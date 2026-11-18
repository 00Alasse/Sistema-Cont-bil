<?php

declare(strict_types=1);

$erroTitulo = is_string($titulo ?? null) ? $titulo : 'Módulo não contratado';
$erroMensagem = is_string($mensagem ?? null) ? $mensagem : 'Este módulo não está ativo para o escritório.';
$erroIcone = 'aviso';
$erroTom = 'aviso';
require dirname(__DIR__) . '/partials/pagina-erro.php';