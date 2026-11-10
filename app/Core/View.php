<?php

declare(strict_types=1);

namespace App\Core;

use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class View
{
    private readonly string $diretorio;

    public function __construct(?string $diretorio = null)
    {
        $this->diretorio = $diretorio ?? dirname(__DIR__) . '/Views';
        if (!is_dir($this->diretorio)) {
            throw new RuntimeException('A pasta de views não existe.');
        }
    }

    /**
     * @param array<string, mixed> $dados
     */
    public function render(string $tpl, array $dados, string $layout): string
    {
        $conteudo = $this->capturar($this->resolverArquivo($tpl), $dados);
        $dadosLayout = $dados;
        $dadosLayout['conteudo'] = $conteudo;

        $layout = str_starts_with($layout, 'layouts/') ? $layout : 'layouts/' . $layout;

        return $this->capturar($this->resolverArquivo($layout), $dadosLayout);
    }

    private function resolverArquivo(string $nome): string
    {
        if ($nome === '' || preg_match('/\A[A-Za-z0-9_\/-]+\z/', $nome) !== 1) {
            throw new InvalidArgumentException('O nome da view é inválido.');
        }

        $raiz = realpath($this->diretorio);
        $arquivo = realpath($this->diretorio . '/' . $nome . '.php');
        if ($raiz === false || $arquivo === false || !str_starts_with($arquivo, $raiz . DIRECTORY_SEPARATOR)) {
            throw new InvalidArgumentException('A view solicitada não existe.');
        }

        return $arquivo;
    }

    /**
     * @param array<string, mixed> $dados
     */
    private function capturar(string $arquivo, array $dados): string
    {
        $nivelInicial = ob_get_level();
        ob_start();

        try {
            (static function (string $__arquivo, array $__dados): void {
                extract($__dados, EXTR_SKIP);
                require $__arquivo;
            })($arquivo, $dados);

            if (ob_get_level() !== $nivelInicial + 1) {
                throw new RuntimeException('A view alterou indevidamente o buffer de saída.');
            }

            $conteudo = ob_get_clean();
            if ($conteudo === false) {
                throw new RuntimeException('Não foi possível capturar a saída da view.');
            }

            return $conteudo;
        } catch (Throwable $erro) {
            while (ob_get_level() > $nivelInicial) {
                ob_end_clean();
            }

            throw $erro;
        }
    }
}
