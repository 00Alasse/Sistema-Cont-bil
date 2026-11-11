<?php

declare(strict_types=1);

namespace App\Controllers\Nucleo;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Middleware\AuthMiddleware;
use App\Services\Nucleo\AuthService;

final class AuthController extends Controller
{
    private const MENSAGEM_GENERICA = 'E-mail ou senha inválidos';
    private const MENSAGEM_BLOQUEIO = 'Muitas tentativas. Tente novamente em alguns minutos.';

    public function mostrarLogin(Request $request): Response
    {
        return $this->paginaLogin(200, [], []);
    }

    public function entrar(Request $request): Response
    {
        $email = $request->input('email');
        $senha = $request->input('senha');
        $email = is_string($email) ? trim($email) : '';
        $senha = is_string($senha) ? $senha : '';

        $erros = Validator::validar(
            ['email' => $email, 'senha' => $senha],
            [
                'email' => ['rotulo' => 'E-mail', 'regras' => 'obrigatorio|email|max:150'],
                'senha' => ['rotulo' => 'Senha', 'regras' => 'obrigatorio'],
            ]
        );
        if ($erros !== []) {
            return $this->paginaLogin(422, $erros, ['email' => $email]);
        }

        $auth = AuthService::padrao();
        $ip = $request->ip() ?? '0.0.0.0';
        if ($auth->bloqueado($email, $ip)) {
            return $this->paginaLogin(429, [], ['email' => $email], self::MENSAGEM_BLOQUEIO);
        }

        $usuario = $auth->autenticar($email, $senha, $ip);
        if ($usuario === null) {
            return $this->paginaLogin(422, [], ['email' => $email], self::MENSAGEM_GENERICA);
        }

        $sessao = Session::atual();
        $auth->abrirSessao($sessao, $usuario);

        return Response::redirecionar($this->destinoAposLogin($sessao, $auth->destinoInicial($usuario)));
    }

    public function sair(Request $request): Response
    {
        Session::atual()->destruir();

        return Response::redirecionar(url('/login'));
    }

    /**
     * Página mínima apenas para os testes E2E (a rota só existe com APP_ENV=testing): prova que o
     * AuthMiddleware entrega o usuário e oferece um formulário de logout com token CSRF.
     */
    public function paginaProtegidaDeTeste(Request $request): Response
    {
        return Response::html(
            '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><title>Área protegida</title></head><body>'
            . '<p>Área protegida de ' . e($request->usuario()->nome) . ' (' . e($request->usuario()->perfil) . ')</p>'
            . '<form method="post" action="' . e(url('/logout')) . '">' . csrf_campo()
            . '<button type="submit">Sair</button></form></body></html>'
        );
    }

    /**
     * @param array<string, list<string>> $erros
     * @param array<string, mixed> $antigos
     */
    private function paginaLogin(int $status, array $erros, array $antigos, ?string $aviso = null): Response
    {
        $mensagens = Session::atual()->consumirFlash();
        if ($aviso !== null) {
            $mensagens[] = ['tipo' => 'erro', 'mensagem' => $aviso];
        }

        return $this->view('nucleo/login', [
            'titulo' => 'Entrar',
            'erros' => $erros,
            'antigos' => $antigos,
            'mensagens' => $mensagens,
        ], $status, 'auth');
    }

    private function destinoAposLogin(Session $sessao, string $padrao): string
    {
        $caminho = $sessao->obter(AuthMiddleware::CHAVE_DESTINO_CAMINHO);
        $query = $sessao->obter(AuthMiddleware::CHAVE_DESTINO_QUERY, []);
        $sessao->remover(AuthMiddleware::CHAVE_DESTINO_CAMINHO);
        $sessao->remover(AuthMiddleware::CHAVE_DESTINO_QUERY);

        $valido = is_string($caminho) && str_starts_with($caminho, '/') && !str_starts_with($caminho, '//')
            && !in_array($caminho, ['/login', '/logout'], true);
        if (!$valido) {
            return url($padrao);
        }

        return url($caminho, is_array($query) ? $query : []);
    }
}
