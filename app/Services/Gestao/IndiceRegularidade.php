<?php

declare(strict_types=1);

namespace App\Services\Gestao;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Índice de Regularidade (ARCHITECTURE §10.4): cálculo puro sobre contadores já agregados.
 * Não acessa banco nem relógio; pesos e limites vêm de config/regras/regularidade.php.
 * O índice é gerencial e administrativo: não atesta regularidade fiscal (ADR-015).
 */
final class IndiceRegularidade
{
    public const VERDE = 'verde';

    public const AMARELO = 'amarelo';

    public const VERMELHO = 'vermelho';

    public const SEM_HISTORICO = 'sem_historico';

    private const REGRAS = [
        'janela_meses',
        'peso_atrasada',
        'peso_documento_vencido',
        'limite_vermelho',
        'limite_verde',
        'dias_atraso_critico',
    ];

    private const CONTADORES = ['total', 'no_prazo', 'atrasadas', 'atrasadas_criticas', 'docs_vencidos'];

    /** @var array<string, int> */
    private readonly array $regras;

    /**
     * @param array<string, mixed> $regras
     */
    public function __construct(array $regras)
    {
        $this->regras = self::inteirosNaoNegativos($regras, self::REGRAS, 'Regra');
    }

    /**
     * Datas que a consulta usa, no formato Y-m-d:
     * janela = [inicio, fim]; atraso crítico = vencimento < critico.
     *
     * @return array{hoje: string, inicio: string, fim: string, critico: string}
     */
    public function datasDeReferencia(DateTimeImmutable $hoje): array
    {
        $hoje = $hoje->setTime(0, 0);

        return [
            'hoje' => $hoje->format('Y-m-d'),
            'inicio' => self::subtrairMeses($hoje, $this->regras['janela_meses'])->format('Y-m-d'),
            'fim' => $hoje->modify('-1 day')->format('Y-m-d'),
            'critico' => $hoje->modify('-' . $this->regras['dias_atraso_critico'] . ' days')->format('Y-m-d'),
        ];
    }

    /**
     * @param array<string, mixed> $contadores total, no_prazo, atrasadas, atrasadas_criticas, docs_vencidos
     * @return array{score: int|null, semaforo: string, motivos: list<string>, contadores: array<string, int|null>}
     */
    public function calcular(array $contadores): array
    {
        $c = self::inteirosNaoNegativos($contadores, self::CONTADORES, 'Contador');
        if ($c['no_prazo'] > $c['total'] || $c['atrasadas_criticas'] > $c['atrasadas']) {
            throw new InvalidArgumentException('Contadores do índice incoerentes.');
        }

        $pontualidade = $c['total'] === 0 ? null : self::percentual($c['no_prazo'], $c['total']);
        $score = $pontualidade === null ? null : max(0, min(100,
            $pontualidade
            - $this->regras['peso_atrasada'] * $c['atrasadas']
            - $this->regras['peso_documento_vencido'] * $c['docs_vencidos']
        ));

        return [
            'score' => $score,
            'semaforo' => $this->semaforo($score, $c['atrasadas'], $c['atrasadas_criticas']),
            'motivos' => $this->motivos($c, $pontualidade),
            'contadores' => [
                'total' => $c['total'],
                'no_prazo' => $c['no_prazo'],
                'pontualidade' => $pontualidade,
                'atrasadas' => $c['atrasadas'],
                'atrasadas_criticas' => $c['atrasadas_criticas'],
                'docs_vencidos' => $c['docs_vencidos'],
            ],
        ];
    }

    /**
     * Ordem: vermelho, verde, amarelo. As condições de vermelho e verde não se sobrepõem.
     * Sem histórico (score nulo) só vira vermelho por atraso crítico (D1).
     */
    private function semaforo(?int $score, int $atrasadas, int $criticas): string
    {
        if ($criticas > 0) {
            return self::VERMELHO;
        }
        if ($score === null) {
            return self::SEM_HISTORICO;
        }
        if ($score < $this->regras['limite_vermelho']) {
            return self::VERMELHO;
        }
        if ($score >= $this->regras['limite_verde'] && $atrasadas === 0) {
            return self::VERDE;
        }

        return self::AMARELO;
    }

    /**
     * Só os motivos que se aplicam, na ordem combinada (D2).
     *
     * @param array<string, int> $c
     * @return list<string>
     */
    private function motivos(array $c, ?int $pontualidade): array
    {
        $motivos = [];
        if ($c['atrasadas'] > 0) {
            $motivos[] = self::contagem($c['atrasadas'], 'obrigação atrasada', 'obrigações atrasadas');
        }
        $dias = $this->regras['dias_atraso_critico'];
        if ($c['atrasadas_criticas'] === 1) {
            $motivos[] = 'Obrigação atrasada há mais de ' . $dias . ' dias';
        } elseif ($c['atrasadas_criticas'] > 1) {
            $motivos[] = $c['atrasadas_criticas'] . ' obrigações atrasadas há mais de ' . $dias . ' dias';
        }
        if ($c['docs_vencidos'] > 0) {
            $motivos[] = self::contagem($c['docs_vencidos'], 'documento vencido', 'documentos vencidos');
        }
        $meses = $this->regras['janela_meses'];
        $motivos[] = $pontualidade === null
            ? 'Sem obrigações vencidas nos últimos ' . $meses . ' meses'
            : 'Pontualidade de ' . $pontualidade . '% em ' . $meses . ' meses';

        return $motivos;
    }

    private static function contagem(int $quantidade, string $singular, string $plural): string
    {
        return $quantidade . ' ' . ($quantidade === 1 ? $singular : $plural);
    }

    /**
     * round(parte / total × 100) só com inteiros (meio arredonda para cima), sem float.
     */
    private static function percentual(int $parte, int $total): int
    {
        return intdiv(200 * $parte + $total, 2 * $total);
    }

    /**
     * Mesmo dia N meses antes; se o dia não existe no mês de destino, usa o último dia
     * desse mês (29/02/2028 − 12 meses = 28/02/2027).
     */
    private static function subtrairMeses(DateTimeImmutable $data, int $meses): DateTimeImmutable
    {
        $mesDestino = $data->modify('first day of this month')->modify('-' . $meses . ' months');
        $dia = min((int) $data->format('j'), (int) $mesDestino->format('t'));

        return $mesDestino->setDate((int) $mesDestino->format('Y'), (int) $mesDestino->format('n'), $dia);
    }

    /**
     * @param array<string, mixed> $valores
     * @param list<string> $chaves
     * @return array<string, int>
     */
    private static function inteirosNaoNegativos(array $valores, array $chaves, string $nome): array
    {
        $lidos = [];
        foreach ($chaves as $chave) {
            $valor = $valores[$chave] ?? null;
            if (!is_int($valor) || $valor < 0) {
                throw new InvalidArgumentException($nome . ' "' . $chave . '" ausente ou inválido.');
            }
            $lidos[$chave] = $valor;
        }

        return $lidos;
    }
}
