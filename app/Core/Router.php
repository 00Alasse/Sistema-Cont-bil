<?php

declare(strict_types=1);

namespace App\Core;

use App\Exceptions\MetodoNaoPermitidoException;
use App\Exceptions\NaoEncontradoException;
use InvalidArgumentException;
use LogicException;

final class Router
{
    /**
     * @var list<array{
     *     metodo: string,
     *     padrao: string,
     *     regex: string,
     *     nomes: list<string>,
     *     handler: array<int|string, mixed>,
     *     middlewares: array<int, mixed>,
     *     estatica: bool
     * }>
     */
    private array $rotas = [];

    /**
     * @param array<int|string, mixed> $handler
     * @param array<int, mixed> $middlewares
     */
    public function adicionar(string $metodo, string $padrao, array $handler, array $middlewares): void
    {
        $metodo = strtoupper($metodo);
        if (preg_match('/\A[A-Z]+\z/', $metodo) !== 1) {
            throw new InvalidArgumentException('O método HTTP da rota é inválido.');
        }

        $padrao = self::normalizarCaminho($padrao);
        [$regex, $nomes] = $this->compilarPadrao($padrao);
        foreach ($this->rotas as $rota) {
            if ($rota['metodo'] === $metodo && $rota['padrao'] === $padrao) {
                throw new LogicException('A rota ' . $metodo . ' ' . $padrao . ' já foi registrada.');
            }
        }

        $this->rotas[] = [
            'metodo' => $metodo,
            'padrao' => $padrao,
            'regex' => $regex,
            'nomes' => $nomes,
            'handler' => $handler,
            'middlewares' => $middlewares,
            'estatica' => $nomes === [],
        ];
    }

    public function resolver(string $metodo, string $caminho): RotaResolvida
    {
        $metodo = strtoupper($metodo);
        $caminho = self::normalizarCaminho($caminho);
        $metodosPermitidos = [];

        foreach ($this->rotas as $rota) {
            if (preg_match($rota['regex'], $caminho, $capturas) !== 1) {
                continue;
            }

            $metodosPermitidos[] = $rota['metodo'];
            if ($rota['metodo'] !== $metodo) {
                continue;
            }

            $parametros = [];
            foreach ($rota['nomes'] as $indice => $nome) {
                $parametros[$nome] = (int) $capturas[$indice + 1];
            }

            return new RotaResolvida($rota['handler'], $parametros, $rota['middlewares']);
        }

        if ($metodosPermitidos !== []) {
            throw new MetodoNaoPermitidoException(array_values(array_unique($metodosPermitidos)));
        }

        throw new NaoEncontradoException();
    }

    /**
     * @return array{string, list<string>}
     */
    private function compilarPadrao(string $padrao): array
    {
        $partes = preg_split('/(\{[A-Za-z_][A-Za-z0-9_]*\})/', $padrao, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($partes === false) {
            throw new InvalidArgumentException('Não foi possível interpretar o padrão da rota.');
        }

        $regex = '';
        $nomes = [];
        foreach ($partes as $parte) {
            if (preg_match('/\A\{([A-Za-z_][A-Za-z0-9_]*)\}\z/', $parte, $captura) === 1) {
                $nome = $captura[1];
                if (in_array($nome, $nomes, true)) {
                    throw new InvalidArgumentException('O parâmetro {' . $nome . '} está repetido na rota.');
                }

                $nomes[] = $nome;
                $regex .= '([0-9]+)';
                continue;
            }

            if (str_contains($parte, '{') || str_contains($parte, '}')) {
                throw new InvalidArgumentException('O parâmetro do padrão da rota é inválido.');
            }

            $regex .= preg_quote($parte, '#');
        }

        return ['#\A' . $regex . '\z#D', $nomes];
    }

    private static function normalizarCaminho(string $caminho): string
    {
        $caminho = preg_replace('#/+#', '/', $caminho) ?? '/';
        $caminho = '/' . ltrim($caminho, '/');

        return $caminho === '/' ? '/' : rtrim($caminho, '/');
    }
}
