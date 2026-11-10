<?php

declare(strict_types=1);

namespace App\Core;

interface CartaoPainel
{
    public function titulo(): string;

    /**
     * @return array<string, mixed>
     */
    public function dados(UsuarioLogado $usuario): array;

    public function template(): string;

    public function ordem(): int;
}
