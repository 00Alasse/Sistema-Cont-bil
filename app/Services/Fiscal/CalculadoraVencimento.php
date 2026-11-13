<?php

declare(strict_types=1);

namespace App\Services\Fiscal;

use App\Support\Competencia;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Fórmula de ARCHITECTURE §10.2, sem nenhum ajuste além dela:
 * vencimento = mês(competência + meses_apos_competencia), no dia
 * min(dia_vencimento, último dia desse mês). Feriados e fins de semana não
 * são considerados; o usuário ajusta a data manualmente quando precisar.
 */
final class CalculadoraVencimento
{
    public function calcular(Competencia $competencia, int $diaVencimento, int $mesesAposCompetencia): DateTimeImmutable
    {
        // Mesmos limites dos CHECKs de tipos_obrigacao.
        if ($diaVencimento < 1 || $diaVencimento > 31) {
            throw new InvalidArgumentException('O dia de vencimento deve estar entre 1 e 31.');
        }
        if ($mesesAposCompetencia < 0 || $mesesAposCompetencia > 12) {
            throw new InvalidArgumentException('Os meses após a competência devem estar entre 0 e 12.');
        }

        $mesDoVencimento = $competencia->somarMeses($mesesAposCompetencia);
        $ultimoDia = (int) $mesDoVencimento->ultimoDia()->format('j');

        return $mesDoVencimento->primeiroDia()->setDate(
            $mesDoVencimento->ano,
            $mesDoVencimento->mes,
            min($diaVencimento, $ultimoDia)
        );
    }
}
