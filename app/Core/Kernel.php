<?php

declare(strict_types=1);

namespace App\Core;

use App\Exceptions\AcessoNegadoException;
use App\Exceptions\ConflitoException;
use App\Exceptions\CsrfException;
use App\Exceptions\MetodoNaoPermitidoException;
use App\Exceptions\ModuloInativoException;
use App\Exceptions\NaoEncontradoException;
use DateTimeImmutable;
use DateTimeZone;
use LogicException;
use Throwable;

final class Kernel
{
    /**
     * O mapa de middlewares será preenchido nas tarefas F07–F09.
     *
     * @var array<string, callable>
     */
    private array $middlewares = [];

    public function __construct(
        private readonly Router $router,
        private readonly Container $container,
        private readonly View $view,
        private readonly ?string $diretorioLogs = null
    ) {
    }

    public function tratar(Request $request): Response
    {
        try {
            $rota = $this->router->resolver($request->metodo(), $request->caminho());
            $this->validarMiddlewares($rota);

            [$classe, $acao] = $rota->handler;
            if (!is_string($classe) || !is_string($acao)) {
                throw new LogicException('O handler da rota é inválido.');
            }

            $controller = $this->container->get($classe);
            if (!is_callable([$controller, $acao])) {
                throw new LogicException('A ação da rota não pode ser chamada.');
            }

            $argumentos = array_merge([$request], array_values($rota->parametros));
            $resposta = call_user_func_array([$controller, $acao], $argumentos);
            if (!$resposta instanceof Response) {
                throw new LogicException('A ação do controller deve retornar uma Response.');
            }

            return $resposta;
        } catch (Throwable $erro) {
            return $this->responderExcecao($erro);
        }
    }

    private function validarMiddlewares(RotaResolvida $rota): void
    {
        foreach ($rota->middlewares as $middleware) {
            if (!isset($this->middlewares[$middleware])) {
                throw new LogicException('Middleware não registrado: ' . $middleware);
            }
        }
    }

    private function responderExcecao(Throwable $erro): Response
    {
        if ($erro instanceof AcessoNegadoException) {
            return $this->respostaErro(403, 'erros/403', [
                'titulo' => 'Acesso negado',
                'mensagem' => 'Você não tem permissão para acessar esta página.',
            ]);
        }

        if ($erro instanceof ModuloInativoException) {
            return $this->respostaErro(403, 'erros/modulo-inativo', [
                'titulo' => 'Módulo não contratado',
                'mensagem' => 'Este módulo não está ativo para o escritório.',
            ]);
        }

        if ($erro instanceof ConflitoException) {
            return $this->respostaErro(409, 'erros/409', [
                'titulo' => 'Conflito',
                'mensagem' => $erro->getMessage(),
            ]);
        }

        if ($erro instanceof CsrfException) {
            return $this->respostaErro(419, 'erros/419', [
                'titulo' => 'Solicitação expirada',
                'mensagem' => 'Não foi possível confirmar esta solicitação. Atualize a página e tente novamente.',
            ]);
        }

        if ($erro instanceof MetodoNaoPermitidoException) {
            return $this->respostaErro(405, 'erros/405', [
                'titulo' => 'Método não permitido',
                'mensagem' => 'O método usado não é permitido para esta página.',
                'metodosPermitidos' => $erro->metodosPermitidos,
            ])->comHeader('Allow', implode(', ', $erro->metodosPermitidos));
        }

        if ($erro instanceof NaoEncontradoException) {
            return $this->respostaErro(404, 'erros/404', [
                'titulo' => 'Página não encontrada',
                'mensagem' => 'O recurso solicitado não foi encontrado.',
            ]);
        }

        self::registrarErro($erro, $this->diretorioLogs);

        return $this->respostaErro(500, 'erros/500', [
            'titulo' => 'Erro interno',
            'mensagem' => self::debugAtivo()
                ? $erro->getMessage()
                : 'Ocorreu um erro inesperado. Tente novamente mais tarde.',
        ]);
    }

    public static function responderFalhaInicializacao(
        Throwable $erro,
        ?string $diretorioViews = null,
        ?string $diretorioLogs = null
    ): Response {
        self::registrarErro($erro, $diretorioLogs);
        $mensagem = self::debugAtivo()
            ? $erro->getMessage()
            : 'Ocorreu um erro inesperado. Tente novamente mais tarde.';

        try {
            $view = new View($diretorioViews);

            return Response::html($view->render('erros/500', [
                'titulo' => 'Erro interno',
                'mensagem' => $mensagem,
            ], 'erro'), 500);
        } catch (Throwable $erroDeRenderizacao) {
            self::registrarErro($erroDeRenderizacao, $diretorioLogs);

            return Response::html(self::htmlErroInicializacao($mensagem), 500);
        }
    }

    /**
     * @param array<string, mixed> $dados
     */
    private function respostaErro(int $status, string $template, array $dados): Response
    {
        return Response::html($this->view->render($template, $dados, 'erro'), $status);
    }

    private static function debugAtivo(): bool
    {
        try {
            return filter_var(Config::obter('app.debug', false), FILTER_VALIDATE_BOOLEAN);
        } catch (Throwable) {
            error_log('Não foi possível carregar a configuração de depuração da aplicação.');

            return false;
        }
    }

    private static function registrarErro(Throwable $erro, ?string $diretorioLogs = null): void
    {
        try {
            $diretorio = $diretorioLogs ?? dirname(__DIR__, 2) . '/storage/logs';
            if (!is_dir($diretorio) && !mkdir($diretorio, 0700, true) && !is_dir($diretorio)) {
                error_log('Não foi possível criar a pasta de logs da aplicação.');

                return;
            }

            $zona = Config::obter('app.timezone', 'America/Sao_Paulo');
            $zona = is_string($zona) ? $zona : 'America/Sao_Paulo';
            $agora = new DateTimeImmutable('now', new DateTimeZone($zona));
            $linha = '[' . $agora->format('Y-m-d H:i:s') . '] ' . $erro::class . PHP_EOL;
            $arquivo = rtrim($diretorio, '/\\') . '/app-' . $agora->format('Y-m-d') . '.log';

            if (file_put_contents($arquivo, $linha, FILE_APPEND | LOCK_EX) === false) {
                error_log('Não foi possível gravar um erro no log da aplicação.');
            }
        } catch (Throwable) {
            error_log('Não foi possível gravar um erro no log da aplicação.');
        }
    }

    private static function htmlErroInicializacao(string $mensagem): string
    {
        return '<!doctype html><html lang="pt-BR"><meta charset="UTF-8">'
            . '<title>Erro interno</title><body><main><h1>Erro interno</h1><p>'
            . htmlspecialchars($mensagem, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '</p></main></body></html>';
    }
}
