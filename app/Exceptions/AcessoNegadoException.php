<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class AcessoNegadoException extends RuntimeException
{
    public function __construct(string $mensagem = 'O acesso a este recurso não é permitido.')
    {
        parent::__construct($mensagem);
    }
}
