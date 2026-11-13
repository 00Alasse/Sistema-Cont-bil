<?php

declare(strict_types=1);

namespace App\Services\Gestao;

use App\Core\Config;
use App\Core\Database;
use App\Support\Relogio;
use App\Support\RelogioSistema;
use PDO;

/**
 * Índice de Regularidade para a carteira do escritório, com UMA consulta agregada
 * (sem N+1), qualquer que seja o número de empresas. As regras ficam em IndiceRegularidade.
 *
 * Documentos vencidos valem 0 enquanto a DO01 não existir (decisão D5 da GE01).
 */
final class IndiceRegularidadeService
{
    private const ABERTA = "o.status IN ('PENDENTE', 'EM_ANDAMENTO', 'AGUARDANDO_CLIENTE')";

    private const NA_JANELA = "o.status <> 'DISPENSADA' AND o.vencimento BETWEEN ref.inicio AND ref.fim";

    /**
     * As datas de referência entram uma única vez, na tabela derivada "ref" (o PDO com
     * prepares nativos não aceita repetir um parâmetro nomeado). O LEFT JOIN mantém as
     * empresas sem obrigações, com contadores zerados.
     */
    private const SQL = 'SELECT e.id AS empresa_id,
            COALESCE(SUM(' . self::NA_JANELA . '), 0) AS total,
            COALESCE(SUM(' . self::NA_JANELA . " AND o.status = 'ENTREGUE'
                AND DATE(o.entregue_em) <= o.vencimento), 0) AS no_prazo,
            COALESCE(SUM(" . self::ABERTA . ' AND o.vencimento < ref.hoje), 0) AS atrasadas,
            COALESCE(SUM(' . self::ABERTA . ' AND o.vencimento < ref.critico), 0) AS atrasadas_criticas
        FROM empresas e
        CROSS JOIN (SELECT CAST(:hoje AS DATE) AS hoje, CAST(:inicio AS DATE) AS inicio,
                           CAST(:fim AS DATE) AS fim, CAST(:critico AS DATE) AS critico) ref
        LEFT JOIN obrigacoes o ON o.empresa_id = e.id AND o.escritorio_id = e.escritorio_id
        WHERE e.escritorio_id = :escritorio_id';

    private readonly Relogio $relogio;

    private readonly PDO $db;

    private readonly IndiceRegularidade $indice;

    public function __construct(?Relogio $relogio = null, ?PDO $db = null, ?IndiceRegularidade $indice = null)
    {
        $this->relogio = $relogio ?? new RelogioSistema();
        $this->db = $db ?? Database::conexao();
        $this->indice = $indice ?? new IndiceRegularidade(Config::regras('regularidade'));
    }

    /**
     * Sem lista: todas as empresas ATIVAS do escritório. Com lista: as empresas pedidas
     * (ativas ou inativas) que pertencem ao escritório; IDs de fora são ignorados.
     *
     * @param list<int>|null $empresaIds
     * @return array<int, array{score: int|null, semaforo: string, motivos: list<string>, contadores: array<string, int|null>}>
     *         indexado por empresa_id
     */
    public function calcularCarteira(int $escritorioId, ?array $empresaIds = null): array
    {
        $parametros = ['escritorio_id' => $escritorioId] + $this->indice->datasDeReferencia($this->relogio->hoje());
        $sql = self::SQL;

        if ($empresaIds === null) {
            $sql .= " AND e.status = 'ATIVA'";
        } else {
            $ids = array_values(array_unique(array_filter($empresaIds, static fn (int $id): bool => $id > 0)));
            if ($ids === []) {
                return [];
            }
            // Um placeholder numerado por ID: nada vindo de fora é concatenado ao SQL.
            $marcadores = [];
            foreach ($ids as $posicao => $id) {
                $marcadores[] = ':empresa' . $posicao;
                $parametros['empresa' . $posicao] = $id;
            }
            $sql .= ' AND e.id IN (' . implode(', ', $marcadores) . ')';
        }

        $stmt = $this->db->prepare($sql . ' GROUP BY e.id ORDER BY e.id');
        $stmt->execute($parametros);

        $resultado = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $linha) {
            $resultado[(int) $linha['empresa_id']] = $this->indice->calcular([
                'total' => (int) $linha['total'],
                'no_prazo' => (int) $linha['no_prazo'],
                'atrasadas' => (int) $linha['atrasadas'],
                'atrasadas_criticas' => (int) $linha['atrasadas_criticas'],
                'docs_vencidos' => 0,
            ]);
        }

        return $resultado;
    }

    /**
     * Índice de uma empresa; null se ela não pertence ao escritório.
     *
     * @return array{score: int|null, semaforo: string, motivos: list<string>, contadores: array<string, int|null>}|null
     */
    public function calcular(int $escritorioId, int $empresaId): ?array
    {
        return $this->calcularCarteira($escritorioId, [$empresaId])[$empresaId] ?? null;
    }
}
