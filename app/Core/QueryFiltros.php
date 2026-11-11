<?php

declare(strict_types=1);

namespace App\Core;

use InvalidArgumentException;

final class QueryFiltros
{
    public static function like(string $valor): string
    {
        return str_replace(
            ['\\', '%', '_'],
            ['\\\\', '\\%', '\\_'],
            $valor
        );
    }

    /**
     * @param list<string> $permitidos
     */
    public static function ordenacao(?string $param, array $permitidos, string $padrao): string
    {
        self::validarPermitidos($permitidos);
        $padraoSeguro = self::interpretarOrdenacao($padrao, $permitidos);
        if ($padraoSeguro === null) {
            throw new InvalidArgumentException('A ordenação padrão não é permitida.');
        }

        if ($param === null) {
            return $padraoSeguro;
        }

        return self::interpretarOrdenacao($param, $permitidos) ?? $padraoSeguro;
    }

    /**
     * @param list<string> $permitidos
     */
    private static function validarPermitidos(array $permitidos): void
    {
        if ($permitidos === []) {
            throw new InvalidArgumentException('A lista de ordenação permitida é inválida.');
        }

        foreach ($permitidos as $coluna) {
            if (
                preg_match(
                    '/\A[A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z_][A-Za-z0-9_]*)*\z/',
                    $coluna
                ) !== 1
            ) {
                throw new InvalidArgumentException('Uma coluna permitida para ordenação é inválida.');
            }
        }
    }

    /**
     * @param list<string> $permitidos
     */
    private static function interpretarOrdenacao(string $valor, array $permitidos): ?string
    {
        if (
            preg_match(
                '/\A([A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z_][A-Za-z0-9_]*)*)\s+(ASC|DESC)\z/i',
                trim($valor),
                $correspondencias
            ) !== 1
            || !in_array($correspondencias[1], $permitidos, true)
        ) {
            return null;
        }

        return $correspondencias[1] . ' ' . strtoupper($correspondencias[2]);
    }
}
