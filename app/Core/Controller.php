<?php

declare(strict_types=1);

namespace App\Core;

class Controller
{
    public function __construct(protected readonly View $viewEngine)
    {
    }

    /**
     * @param array<string, mixed> $dados
     */
    protected function view(
        string $tpl,
        array $dados = [],
        int $status = 200,
        string $layout = 'app'
    ): Response {
        return Response::html($this->viewEngine->render($tpl, $dados, $layout), $status);
    }

    protected function redirecionar(string $url, ?string $flashSucesso = null): Response
    {
        if ($flashSucesso !== null) {
            Session::atual()->flash('sucesso', $flashSucesso);
        }

        return Response::redirecionar(url($url));
    }

    /**
     * @param array<string, list<string>> $erros
     * @param array<string, mixed> $antigos
     * @param array<string, mixed> $dados
     */
    protected function voltarComErros(string $tpl, array $erros, array $antigos, array $dados = []): Response
    {
        $dados = array_merge($dados, ['erros' => $erros, 'antigos' => $antigos]);

        return Response::html($this->viewEngine->render($tpl, $dados, 'app'), 422);
    }
}
