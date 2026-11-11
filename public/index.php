<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Container;
use App\Core\Kernel;
use App\Core\Modulos;
use App\Core\Request;
use App\Core\Router;
use App\Core\View;
use Throwable;

$autoload = dirname(__DIR__) . '/vendor/autoload.php';
$autoloadCarregado = false;

try {
    if (!is_file($autoload)) {
        throw new RuntimeException('O autoloader da aplicação não existe.');
    }

    require $autoload;
    $autoloadCarregado = true;
    require_once dirname(__DIR__) . '/app/Core/helpers.php';

    $router = new Router();
    $diretorioRaiz = dirname(__DIR__);
    (new Modulos($router, $diretorioRaiz . '/config/modules'))->carregar();

    $kernel = new Kernel(
        $router,
        new Container(),
        new View($diretorioRaiz . '/app/Views')
    );
    $request = Request::capturar((string) Config::obter('app.base_path', ''));
    $kernel->tratar($request)->enviar();
} catch (Throwable $erro) {
    $kernelDisponivel = class_exists(Kernel::class, false);
    if (!$kernelDisponivel && $autoloadCarregado) {
        try {
            $kernelDisponivel = class_exists(Kernel::class);
        } catch (Throwable) {
        }
    }

    if (!$kernelDisponivel) {
        error_log('Falha ao inicializar a aplicação.');
    } else {
        try {
            Kernel::responderFalhaInicializacao($erro)->enviar();
            return;
        } catch (Throwable) {
            error_log('Falha ao renderizar a resposta de erro da aplicação.');
        }
    }

    http_response_code(500);
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header(
        "Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; "
        . "img-src 'self' data:; frame-ancestors 'none'; form-action 'self'"
    );
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!doctype html><html lang="pt-BR"><meta charset="UTF-8">'
        . '<title>Erro interno</title><body><main><h1>Erro interno</h1>'
        . '<p>Ocorreu um erro inesperado. Tente novamente mais tarde.</p>'
        . '</main></body></html>';
}
