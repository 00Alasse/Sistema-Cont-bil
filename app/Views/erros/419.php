<?php

declare(strict_types=1);

$erroTitulo = is_string($titulo ?? null) ? $titulo : 'Solicitação expirada';
$erroMensagem = is_string($mensagem ?? null) ? $mensagem : 'Não foi possível confirmar esta solicitação. Atualize a página e tente novamente.';
$erroIcone = 'aviso';
$erroTom = 'aviso';
require dirname(__DIR__) . '/partials/pagina-erro.php';