<?php

declare(strict_types=1);

namespace App\Support;

final class Cnpj
{
    public static function normalizar(string $cnpj): string
    {
        $normalizado = preg_replace('/[.\/-]|\s|\p{Z}/u', '', $cnpj);
        if ($normalizado === null) {
            return strtoupper($cnpj);
        }

        return strtoupper($normalizado);
    }

    public static function validar(string $cnpj): bool
    {
        $cnpj = self::normalizar($cnpj);
        if (preg_match('/\A[A-Z0-9]{12}[0-9]{2}\z/', $cnpj) !== 1) {
            return false;
        }

        if (preg_match('/\A([A-Z0-9])\1{13}\z/', $cnpj) === 1) {
            return false;
        }

        $base = substr($cnpj, 0, 12);
        $primeiroDigito = self::calcularDigito($base);
        $segundoDigito = self::calcularDigito($base . $primeiroDigito);

        return substr($cnpj, 12) === $primeiroDigito . $segundoDigito;
    }

    public static function formatar(string $cnpj): string
    {
        $cnpj = self::normalizar($cnpj);
        if (strlen($cnpj) !== 14) {
            return $cnpj;
        }

        return substr($cnpj, 0, 2)
            . '.' . substr($cnpj, 2, 3)
            . '.' . substr($cnpj, 5, 3)
            . '/' . substr($cnpj, 8, 4)
            . '-' . substr($cnpj, 12, 2);
    }

    public static function gerarFicticio(int $semente): string
    {
        $parteNumerica = (string) abs($semente % 100_000_000);
        $base = str_pad($parteNumerica, 8, '0', STR_PAD_LEFT) . '0001';
        $primeiroDigito = self::calcularDigito($base);
        $segundoDigito = self::calcularDigito($base . $primeiroDigito);

        return $base . $primeiroDigito . $segundoDigito;
    }

    private static function calcularDigito(string $base): string
    {
        // Manual oficial da Receita Federal: https://www.gov.br/receitafederal/pt-br/centrais-de-conteudo/publicacoes/documentos-tecnicos/cnpj/manual-dv-cnpj.pdf
        $soma = 0;
        $peso = 2;

        for ($indice = strlen($base) - 1; $indice >= 0; $indice--) {
            $valor = ord($base[$indice]) - 48;
            $soma += $valor * $peso;
            $peso = $peso === 9 ? 2 : $peso + 1;
        }

        $resto = $soma % 11;

        return (string) ($resto < 2 ? 0 : 11 - $resto);
    }
}
