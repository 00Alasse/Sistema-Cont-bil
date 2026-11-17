<?php

declare(strict_types=1);

namespace App\Controllers\Cadastro;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Menu;
use App\Core\Modulos;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\View;
use PDO;

final class UsuarioController extends Controller
{
    private readonly PDO $db;

    public function __construct(View $viewEngine)
    {
        parent::__construct($viewEngine);
        $this->db = Database::conexao();
    }

    public function index(Request $request): Response
    {
        $usuario = $request->usuario();
        $stmt = $this->db->prepare(
            'SELECT u.id, u.nome, u.email, u.perfil, u.ativo, u.ultimo_login_em, e.razao_social AS empresa_nome
             FROM usuarios u
             LEFT JOIN empresas e ON e.id = u.empresa_id AND e.escritorio_id = u.escritorio_id
             WHERE u.escritorio_id = :escritorio_id
             ORDER BY u.nome ASC'
        );
        $stmt->execute(['escritorio_id' => $usuario->escritorioId]);
        $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $this->view('cadastro/usuarios/index', array_merge(
            $this->contextoLayout($request, 'Usuários'),
            [
                'usuarios' => $usuarios,
            ]
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function contextoLayout(Request $request, string $titulo): array
    {
        $usuario = $request->usuario();

        return [
            'titulo' => $titulo,
            'usuario' => $usuario,
            'itensMenu' => (new Menu($this->carregarModulos()))->itens($usuario),
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function carregarModulos(): array
    {
        $router = new Router();
        $modulos = new Modulos($router, dirname(__DIR__, 3) . '/config/modules');

        return $modulos->carregar();
    }
}
