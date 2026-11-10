<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class ConflitoException extends RuntimeException
{
    public function __construct(string $mensagem = 'O registro foi alterado por outra operação.')
    {
        parent::__construct($mensagem);
    }
}
