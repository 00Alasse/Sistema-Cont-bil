<?php

declare(strict_types=1);

namespace App\Core;

final class Paginacao
{
    private const ITENS_POR_PAGINA_MAXIMO = 100;

    private function __construct(
        public readonly int $pagina,
        public readonly int $porPagina
    ) {
    }

    public static function deRequest(Request $request, int $porPagina = 20): self
    {
        $pagina = self::normalizarPagina($request->query('pagina', 1));
        $porPagina = min(max(1, $porPagina), self::ITENS_POR_PAGINA_MAXIMO);

        return new self($pagina, $porPagina);
    }

    private static function normalizarPagina(mixed $valor): int
    {
        if (is_int($valor)) {
            return $valor > 0 ? $valor : 1;
        }

        if (!is_string($valor) || preg_match('/\A[0-9]+\z/', $valor) !== 1) {
            return 1;
        }

        $pagina = (int) $valor;

        return $pagina > 0 ? $pagina : 1;
    }
}
