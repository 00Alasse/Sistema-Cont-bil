<?php

declare(strict_types=1);

$erroTitulo = is_string($titulo ?? null) ? $titulo : 'Conflito';
$erroMensagem = is_string($mensagem ?? null) ? $mensagem : 'Não foi possível concluir a operação porque os dados foram alterados.';
$erroIcone = 'aviso';
$erroTom = 'aviso';
require dirname(__DIR__) . '/partials/pagina-erro.php';