<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class ModuloInativoException extends RuntimeException
{
    public function __construct(string $mensagem = 'Este módulo não está ativo para o escritório.')
    {
        parent::__construct($mensagem);
    }
}
