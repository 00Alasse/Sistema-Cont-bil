<?php

declare(strict_types=1);

namespace App\Models\Fiscal;

use App\Core\Model;
use App\Core\Pagina;
use App\Core\Paginacao;
use App\Core\QueryFiltros;

/**
 * Catálogo de tipos de obrigação do escritório. Os prazos são cadastrados pelo próprio
 * escritório: o sistema não conhece nem valida prazos legais (ADR-015).
 */
final class TipoObrigacao extends Model
{
    public const ESFERAS = ['FEDERAL', 'ESTADUAL', 'MUNICIPAL', 'TRABALHISTA', 'OUTRA'];

    public const PERIODICIDADES = ['MENSAL', 'TRIMESTRAL', 'ANUAL', 'EVENTUAL'];

    /** Periodicidades que exigem dia de vencimento (mesma regra do CHECK ck_tipos_dia_obrigatorio). */
    public const PERIODICIDADES_COM_DIA = ['MENSAL', 'TRIMESTRAL'];

    /**
     * @param array<string, mixed> $filtros
     */
    public function listar(int $escritorioId, array $filtros, Paginacao $paginacao): Pagina
    {
        $condicoes = ['t.escritorio_id = :escritorio_id'];
        $parametros = ['escritorio_id' => $escritorioId];

        $busca = $filtros['q'] ?? null;
        if (is_string($busca) && trim($busca) !== '') {
            $condicoes[] = "(t.nome LIKE :termo_nome ESCAPE '\\\\' OR t.descricao LIKE :termo_descricao ESCAPE '\\\\')";
            $parametros['termo_nome'] = '%' . QueryFiltros::like(trim($busca)) . '%';
            $parametros['termo_descricao'] = $parametros['termo_nome'];
        }

        // Só valores conhecidos viram filtro; o resto é ignorado.
        $opcoes = ['esfera' => self::ESFERAS, 'periodicidade' => self::PERIODICIDADES, 'ativo' => ['0', '1']];
        foreach ($opcoes as $filtro => $permitidos) {
            $valor = $filtros[$filtro] ?? null;
            if (is_string($valor) && in_array($valor, $permitidos, true)) {
                $condicoes[] = 't.' . $filtro . ' = :' . $filtro;
                $parametros[$filtro] = $valor;
            }
        }

        $where = implode(' AND ', $condicoes);

        return $this->paginar(
            "SELECT t.*,
                    (SELECT COUNT(*) FROM empresa_tipos_obrigacao v
                      WHERE v.escritorio_id = t.escritorio_id AND v.tipo_obrigacao_id = t.id) AS total_empresas
             FROM tipos_obrigacao t
             WHERE {$where}
             ORDER BY t.nome ASC, t.id ASC",
            "SELECT COUNT(*) FROM tipos_obrigacao t WHERE {$where}",
            $parametros,
            $paginacao
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function buscar(int $escritorioId, int $id): ?array
    {
        return $this->buscarUm(
            'SELECT * FROM tipos_obrigacao WHERE escritorio_id = :escritorio_id AND id = :id',
            ['escritorio_id' => $escritorioId, 'id' => $id]
        );
    }

    /**
     * @param array<string, mixed> $dados
     */
    public function criar(int $escritorioId, array $dados): int
    {
        return $this->inserir(
            'INSERT INTO tipos_obrigacao
                (escritorio_id, nome, descricao, esfera, periodicidade,
                 dia_vencimento, meses_apos_competencia, ativo)
             VALUES
                (:escritorio_id, :nome, :descricao, :esfera, :periodicidade,
                 :dia_vencimento, :meses_apos_competencia, :ativo)',
            array_merge(['escritorio_id' => $escritorioId], $this->colunas($dados))
        );
    }

    /**
     * Sem coluna `versao` no DDL: a última gravação prevalece (decisão aprovada na FI02).
     *
     * @param array<string, mixed> $dados
     */
    public function atualizar(int $escritorioId, int $id, array $dados): void
    {
        $this->executar(
            'UPDATE tipos_obrigacao
             SET nome = :nome,
                 descricao = :descricao,
                 esfera = :esfera,
                 periodicidade = :periodicidade,
                 dia_vencimento = :dia_vencimento,
                 meses_apos_competencia = :meses_apos_competencia,
                 ativo = :ativo
             WHERE escritorio_id = :escritorio_id AND id = :id',
            array_merge($this->colunas($dados), ['escritorio_id' => $escritorioId, 'id' => $id])
        );
    }

    /**
     * Os vínculos com empresas caem em cascata; obrigações existentes bloqueiam (erro 1451).
     */
    public function excluir(int $escritorioId, int $id): int
    {
        return $this->executar(
            'DELETE FROM tipos_obrigacao WHERE escritorio_id = :escritorio_id AND id = :id',
            ['escritorio_id' => $escritorioId, 'id' => $id]
        );
    }

    /**
     * A comparação segue a collation da coluna (sem diferenciar maiúsculas), como a chave única.
     */
    public function nomeJaExiste(int $escritorioId, string $nome, ?int $ignorarId = null): bool
    {
        $sql = 'SELECT 1 FROM tipos_obrigacao WHERE escritorio_id = :escritorio_id AND nome = :nome';
        $parametros = ['escritorio_id' => $escritorioId, 'nome' => $nome];
        if ($ignorarId !== null) {
            $sql .= ' AND id <> :ignorar_id';
            $parametros['ignorar_id'] = $ignorarId;
        }

        return $this->buscarUm($sql . ' LIMIT 1', $parametros) !== null;
    }

    /**
     * @param array<string, mixed> $dados
     * @return array<string, mixed>
     */
    private function colunas(array $dados): array
    {
        return [
            'nome' => $dados['nome'],
            'descricao' => $dados['descricao'],
            'esfera' => $dados['esfera'],
            'periodicidade' => $dados['periodicidade'],
            'dia_vencimento' => $dados['dia_vencimento'],
            'meses_apos_competencia' => $dados['meses_apos_competencia'],
            'ativo' => $dados['ativo'],
        ];
    }
}
