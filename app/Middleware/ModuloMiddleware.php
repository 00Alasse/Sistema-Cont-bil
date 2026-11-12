<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Exceptions\ModuloInativoException;
use InvalidArgumentException;

/**
 * Exige módulo contratável ativo: `modulo:BPO_FINANCEIRO`. Os módulos vêm de UsuarioLogado
 * (montado no servidor a partir de escritorio_modulos), nunca da requisição.
 */
final class ModuloMiddleware
{
    private const MODULOS_VALIDOS = ['BPO_FINANCEIRO', 'CONSULTIVO'];

    public function __construct(private readonly string $modulo)
    {
        if (!in_array($modulo, self::MODULOS_VALIDOS, true)) {
            throw new InvalidArgumentException('Configuração de módulo inválida na rota.');
        }
    }

    /**
     * @param callable(Request): Response $proximo
     */
    public function tratar(Request $request, callable $proximo): Response
    {
        if (!$request->usuario()->temModulo($this->modulo)) {
            throw new ModuloInativoException();
        }

        return $proximo($request);
    }
}
