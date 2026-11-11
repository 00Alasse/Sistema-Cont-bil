<?php

declare(strict_types=1);

namespace App\Core;

use InvalidArgumentException;

final readonly class Pagina
{
    public int $totalPaginas;

    /**
     * @param list<array<string, mixed>> $itens
     */
    public function __construct(
        public array $itens,
        public int $total,
        public int $pagina,
        public int $porPagina
    ) {
        if ($total < 0 || $pagina < 1 || $porPagina < 1) {
            throw new InvalidArgumentException('Os dados da página são inválidos.');
        }

        $this->totalPaginas = $total === 0 ? 0 : intdiv($total - 1, $porPagina) + 1;
    }
}
