<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Menu;
use App\Core\Modulos;
use App\Core\Paginacao;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\Validator;
use App\Core\View;
use App\Models\Avaliacao\Avaliacao;

final class AvaliacaoController extends Controller
{
    private readonly Avaliacao $avaliacoes;

    public function __construct(View $viewEngine)
    {
        parent::__construct($viewEngine);
        $this->avaliacoes = new Avaliacao();
    }

    public function form(Request $request): Response
    {
        $usuario = $request->usuario();
        $layout = $usuario->ehCliente() ? 'portal' : 'app';

        $dados = array_merge($this->contextoLayout($request, 'Avaliar o Sistema'), [
            'antigos' => ['nota' => '5', 'facilidade' => '5', 'comentario' => ''],
            'erros' => [],
        ]);

        return $this->view('avaliacao/form', $dados, 200, $layout);
    }

    public function salvar(Request $request): Response
    {
        $usuario = $request->usuario();
        $dadosInput = [
            'nota' => $request->input('nota'),
            'facilidade' => $request->input('facilidade'),
            'comentario' => is_string($request->input('comentario')) ? trim($request->input('comentario')) : null,
        ];

        $erros = Validator::validar($dadosInput, [
            'nota' => ['rotulo' => 'Nota Geral', 'regras' => 'obrigatorio|inteiro'],
        ]);

        $nota = (int) $dadosInput['nota'];
        if ($nota < 1 || $nota > 5) {
            $erros['nota'][] = 'A nota geral deve estar entre 1 e 5 estrelas.';
        }

        $facilidade = null;
        if (!empty($dadosInput['facilidade'])) {
            $facilidadeInt = (int) $dadosInput['facilidade'];
            if ($facilidadeInt >= 1 && $facilidadeInt <= 5) {
                $facilidade = $facilidadeInt;
            } else {
                $erros['facilidade'][] = 'A nota de facilidade deve estar entre 1 e 5.';
            }
        }

        if ($erros !== []) {
            $layout = $usuario->ehCliente() ? 'portal' : 'app';
            $dados = array_merge($this->contextoLayout($request, 'Avaliar o Sistema'), [
                'antigos' => $dadosInput,
                'erros' => $erros,
            ]);

            return $this->view('avaliacao/form', $dados, 422, $layout);
        }

        $this->avaliacoes->criar(
            $usuario->escritorioId,
            $usuario->id,
            $nota,
            $facilidade,
            $dadosInput['comentario']
        );

        $destino = $usuario->ehCliente() ? '/portal' : '/avaliacoes';
        return $this->redirecionar($destino, 'Muito obrigado pelo seu feedback! Sua avaliação foi registrada com sucesso.');
    }

    public function index(Request $request): Response
    {
        $usuario = $request->usuario();
        $paginacao = Paginacao::deRequest($request, 15);

        $resultado = $this->avaliacoes->listar($usuario->escritorioId, $paginacao);
        $estatisticas = $this->avaliacoes->estatisticas($usuario->escritorioId);

        $dados = array_merge($this->contextoLayout($request, 'Avaliações dos Usuários'), [
            'pagina' => $resultado,
            'estatisticas' => $estatisticas,
        ]);

        return $this->view('avaliacao/index', $dados);
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
            'itensMenu' => (new Menu((new Modulos(new Router()))->carregar()))->itens($usuario),
        ];
    }
}
