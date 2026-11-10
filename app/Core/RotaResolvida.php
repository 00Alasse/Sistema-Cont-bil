<?php

declare(strict_types=1);

namespace App\Core;

final class RotaResolvida
{
    /**
     * @param array<int|string, mixed> $handler
     * @param array<string, int> $parametros
     * @param array<int, mixed> $middlewares
     */
    public function __construct(
        public readonly array $handler,
        public readonly array $parametros,
        public readonly array $middlewares
    ) {
    }
}
