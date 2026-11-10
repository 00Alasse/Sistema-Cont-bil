<?php

declare(strict_types=1);

use App\Core\Env;

$ambiente = Env::obter('APP_ENV', 'local');
$debug = filter_var(Env::obter('APP_DEBUG', 'true'), FILTER_VALIDATE_BOOLEAN);
$bancoTeste = Env::obter('DB_TEST_DATABASE', 'tributo_test');

$configuracoes = [
    'app' => [
        'nome' => Env::obter('APP_NAME', 'Tributo'),
        'env' => $ambiente,
        'debug' => $debug,
        'url' => Env::obter('APP_URL', 'http://localhost/tributo'),
        'base_path' => Env::obter('APP_BASE_PATH', ''),
        'timezone' => Env::obter('APP_TIMEZONE', 'America/Sao_Paulo'),
    ],
    'db' => [
        'host' => Env::obter('DB_HOST', '127.0.0.1'),
        'porta' => (int) Env::obter('DB_PORT', '3306'),
        'banco' => Env::obter('DB_DATABASE', 'tributo'),
        'usuario' => Env::obter('DB_USERNAME', 'root'),
        'senha' => Env::obter('DB_PASSWORD', ''),
        'charset' => Env::obter('DB_CHARSET', 'utf8mb4'),
        'banco_teste' => $bancoTeste,
    ],
    'sessao' => [
        'nome' => Env::obter('SESSION_NAME', 'tributo_sessao'),
        'inatividade_min' => (int) Env::obter('SESSION_IDLE_MINUTES', '30'),
        'cookie_seguro' => filter_var(
            Env::obter('SESSION_SECURE_COOKIE', 'false'),
            FILTER_VALIDATE_BOOLEAN
        ),
    ],
    'login' => [
        'max_tentativas' => (int) Env::obter('LOGIN_MAX_ATTEMPTS', '5'),
        'bloqueio_min' => (int) Env::obter('LOGIN_LOCK_MINUTES', '15'),
    ],
    'upload' => [
        'max_bytes' => (int) Env::obter('UPLOAD_MAX_BYTES', '10485760'),
        'caminho' => Env::obter('UPLOAD_PATH', 'storage/uploads'),
    ],
    'log' => [
        'nivel' => Env::obter('LOG_LEVEL', 'debug'),
        'caminho' => Env::obter('LOG_PATH', 'storage/logs'),
    ],
];

if ($ambiente === 'testing') {
    $configuracoes['db']['banco'] = $bancoTeste;
}

return $configuracoes;
