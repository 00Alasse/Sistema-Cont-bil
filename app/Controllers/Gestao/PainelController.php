<?php

declare(strict_types=1);

namespace App\Controllers\Gestao;

use App\Core\Controller;
use App\Core\Menu;
use App\Core\Modulos;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\View;
use App\Services\Gestao\PainelService;

/**
 * GET /painel (API §8): lê o escopo, pede os indicadores ao PainelService e renderiza. Sem SQL.
 */
final class PainelController extends Controller
{
    private readonly PainelService $painel;

    public function __construct(View $viewEngine)
    {
        parent::__construct($viewEngine);
        $this->painel = new PainelService();
    }

    public function index(Request $request): Response
    {
        $usuario = $request->usuario();
        $escopo = PainelService::escopoEfetivo($usuario, $request->query('escopo'));
        $modulos = (new Modulos(new Router()))->carregar();

        return $this->view('gestao/painel/index', [
            'titulo' => 'Painel',
            'usuario' => $usuario,
            'itensMenu' => (new Menu($modulos))->itens($usuario),
            'painel' => $this->painel->montar($usuario, $escopo),
            'cartoes' => $this->painel->cartoes($usuario, $modulos),
        ]);
    }
}
