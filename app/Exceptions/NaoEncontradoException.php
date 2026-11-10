<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class NaoEncontradoException extends RuntimeException
{
    public function __construct(string $mensagem = 'O recurso solicitado não foi encontrado.')
    {
        parent::__construct($mensagem);
    }
}
