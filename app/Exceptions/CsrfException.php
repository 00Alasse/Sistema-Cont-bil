<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class CsrfException extends RuntimeException
{
    public function __construct(string $mensagem = 'A solicitação não pôde ser validada.')
    {
        parent::__construct($mensagem);
    }
}
