<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class MetodoNaoPermitidoException extends RuntimeException
{
    /**
     * @param list<string> $metodosPermitidos
     */
    public function __construct(public readonly array $metodosPermitidos)
    {
        parent::__construct('O método HTTP não é permitido para este caminho.');
    }
}
