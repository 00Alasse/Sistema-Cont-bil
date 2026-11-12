<?php

declare(strict_types=1);

namespace App\Controllers\Nucleo;

use App\Core\Config;
use App\Core\Controller;
use App\Core\Menu;
use App\Core\Modulos;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\UsuarioLogado;
use App\Exceptions\NaoEncontradoException;

final class LayoutTesteController extends Controller
{
    public function escritorio(Request $request): Response
    {
        $usuario = $request->usuario();

        return $this->renderizar('nucleo/teste-layout-escritorio', 'app', 'Área do escritório', $usuario);
    }

    public function escritorioTeste(Request $request): Response
    {
        $this->exigirAmbienteDeTeste();

        return $this->escritorio($request);
    }

    public function portalTeste(Request $request): Response
    {
        $this->exigirAmbienteDeTeste();
        $usuario = $request->usuario();

        return $this->renderizar('nucleo/teste-layout-portal', 'portal', 'Portal do cliente', $usuario);
    }

    private function exigirAmbienteDeTeste(): void
    {
        if (Config::obter('app.env') !== 'testing') {
            throw new NaoEncontradoException();
        }
    }

    private function renderizar(
        string $template,
        string $layout,
        string $titulo,
        UsuarioLogado $usuario
    ): Response {
        $modulos = (new Modulos(new Router()))->carregar();
        $itensMenu = (new Menu($modulos))->itens($usuario);

        return $this->view($template, [
            'titulo' => $titulo,
            'usuario' => $usuario,
            'itensMenu' => $itensMenu,
        ], 200, $layout);
    }
}
