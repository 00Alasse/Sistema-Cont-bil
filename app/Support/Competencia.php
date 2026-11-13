<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Mês de referência de uma obrigação ("AAAA-MM"). No banco, a competência é sempre
 * gravada como o dia 01 do mês (CHECK ck_obr_competencia).
 */
final class Competencia
{
    private function __construct(
        public readonly int $ano,
        public readonly int $mes
    ) {
    }

    /**
     * Lê o formato da URL e dos formulários: "AAAA-MM".
     */
    public static function deTexto(string $texto): self
    {
        if (preg_match('/\A([0-9]{4})-([0-9]{2})\z/', trim($texto), $partes) !== 1) {
            throw new InvalidArgumentException('A competência deve estar no formato AAAA-MM.');
        }

        $ano = (int) $partes[1];
        $mes = (int) $partes[2];
        if (!checkdate($mes, 1, $ano)) {
            throw new InvalidArgumentException('A competência deve estar no formato AAAA-MM.');
        }

        return new self($ano, $mes);
    }

    /**
     * Lê uma data "AAAA-MM-DD" (por exemplo, a coluna `competencia` do banco).
     */
    public static function deData(string $data): self
    {
        $lida = DateTimeImmutable::createFromFormat('!Y-m-d', $data);
        if ($lida === false || $lida->format('Y-m-d') !== $data) {
            throw new InvalidArgumentException('A data deve estar no formato AAAA-MM-DD.');
        }

        return new self((int) $lida->format('Y'), (int) $lida->format('n'));
    }

    public function primeiroDia(): DateTimeImmutable
    {
        return new DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $this->ano, $this->mes));
    }

    public function ultimoDia(): DateTimeImmutable
    {
        return $this->primeiroDia()->modify('last day of this month');
    }

    /**
     * Soma meses partindo do dia 01, então nunca "transborda" para o mês seguinte
     * (diferente de somar meses a um dia 31).
     */
    public function somarMeses(int $meses): self
    {
        if ($meses < 0) {
            throw new InvalidArgumentException('A quantidade de meses não pode ser negativa.');
        }

        $data = $this->primeiroDia()->modify('+' . $meses . ' months');

        return new self((int) $data->format('Y'), (int) $data->format('n'));
    }

    public function comoTexto(): string
    {
        return sprintf('%04d-%02d', $this->ano, $this->mes);
    }
}
