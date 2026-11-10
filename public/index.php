<?php

declare(strict_types=1);

/**
 * Ponto único de entrada do sistema (front controller).
 *
 * Situação atual (T00): apenas confirma que as dependências foram instaladas.
 * O roteamento e o carregamento dos módulos serão implementados na tarefa F03.
 */

$autoload = dirname(__DIR__) . '/vendor/autoload.php';

if (!is_file($autoload)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Dependências não instaladas. Execute "composer install" na pasta do projeto.';
    exit;
}

require $autoload;

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>-Tributo!</title>
</head>
<body>
    <h1>-Tributo!</h1>
    <p>Estrutura inicial instalada. As funcionalidades estão em desenvolvimento.</p>
</body>
</html>
