<?php

declare(strict_types=1);

namespace App\Models\Fiscal;

use App\Core\Model;

/**
 * Quais tipos de obrigação se aplicam a cada empresa (decisão do contador) e o
 * responsável padrão de cada vínculo.
 */
final class EmpresaTipoObrigacao extends Model
{
    /**
     * Tipos que podem aparecer no formulário da empresa: os ativos e os inativos que já
     * estão vinculados (para não sumirem sem querer).
     *
     * @return list<array<string, mixed>> cada linha traz `vinculado` (0/1) e `responsavel_padrao_id`
     */
    public function listarParaEmpresa(int $escritorioId, int $empresaId): array
    {
        return $this->buscarTodos(
            'SELECT t.id, t.nome, t.descricao, t.esfera, t.periodicidade, t.dia_vencimento,
                    t.meses_apos_competencia, t.ativo,
                    CASE WHEN v.empresa_id IS NULL THEN 0 ELSE 1 END AS vinculado,
                    v.responsavel_padrao_id
             FROM tipos_obrigacao t
             LEFT JOIN empresa_tipos_obrigacao v
                ON v.tipo_obrigacao_id = t.id
               AND v.escritorio_id = t.escritorio_id
               AND v.empresa_id = :empresa_id
             WHERE t.escritorio_id = :escritorio_id
               AND (t.ativo = 1 OR v.empresa_id IS NOT NULL)
             ORDER BY t.nome ASC, t.id ASC',
            ['escritorio_id' => $escritorioId, 'empresa_id' => $empresaId]
        );
    }

    /**
     * IDs aceitos num envio do formulário (mesmo critério de listarParaEmpresa).
     *
     * @return list<int>
     */
    public function idsDisponiveis(int $escritorioId, int $empresaId): array
    {
        return array_map(
            static fn (array $linha): int => (int) $linha['id'],
            $this->listarParaEmpresa($escritorioId, $empresaId)
        );
    }

    /**
     * Bloqueia a linha da empresa até o fim da transação (SELECT ... FOR UPDATE).
     * Duas substituições da mesma empresa passam a acontecer uma depois da outra;
     * empresas diferentes não se bloqueiam. Só tem efeito dentro de uma transação.
     *
     * @return bool false se a empresa não existe neste escritório
     */
    public function bloquearEmpresa(int $escritorioId, int $empresaId): bool
    {
        return $this->buscarUm(
            'SELECT id FROM empresas
             WHERE escritorio_id = :escritorio_id AND id = :empresa_id
             FOR UPDATE',
            ['escritorio_id' => $escritorioId, 'empresa_id' => $empresaId]
        ) !== null;
    }

    /**
     * @param bool $bloquear true dentro da substituição: lê a última versão gravada
     *                       e bloqueia os vínculos que serão alterados
     * @return array<int, int|null> tipo_obrigacao_id => responsavel_padrao_id
     */
    public function listarVinculos(int $escritorioId, int $empresaId, bool $bloquear = false): array
    {
        $linhas = $this->buscarTodos(
            'SELECT tipo_obrigacao_id, responsavel_padrao_id
             FROM empresa_tipos_obrigacao
             WHERE escritorio_id = :escritorio_id AND empresa_id = :empresa_id
             ORDER BY tipo_obrigacao_id ASC'
            . ($bloquear ? ' FOR UPDATE' : ''),
            ['escritorio_id' => $escritorioId, 'empresa_id' => $empresaId]
        );

        $vinculos = [];
        foreach ($linhas as $linha) {
            $responsavel = $linha['responsavel_padrao_id'];
            $vinculos[(int) $linha['tipo_obrigacao_id']] = $responsavel === null ? null : (int) $responsavel;
        }

        return $vinculos;
    }

    /**
     * Deixa a empresa exatamente com os vínculos informados. Faz várias escritas:
     * chame sempre dentro de Database::transacao, depois de bloquearEmpresa
     * (o TipoObrigacaoService faz isso).
     *
     * @param array<int, int|null> $novos tipo_obrigacao_id => responsavel_padrao_id
     * @return array<int, int|null> os vínculos que existiam antes (para a auditoria)
     */
    public function substituir(int $escritorioId, int $empresaId, array $novos): array
    {
        $atuais = $this->listarVinculos($escritorioId, $empresaId, true);

        foreach (array_diff_key($atuais, $novos) as $tipoId => $responsavel) {
            $this->executar(
                'DELETE FROM empresa_tipos_obrigacao
                 WHERE escritorio_id = :escritorio_id AND empresa_id = :empresa_id AND tipo_obrigacao_id = :tipo_id',
                ['escritorio_id' => $escritorioId, 'empresa_id' => $empresaId, 'tipo_id' => $tipoId]
            );
        }

        foreach ($novos as $tipoId => $responsavel) {
            $parametros = [
                'escritorio_id' => $escritorioId,
                'empresa_id' => $empresaId,
                'tipo_id' => $tipoId,
                'responsavel' => $responsavel,
            ];
            if (!array_key_exists($tipoId, $atuais)) {
                $this->executar(
                    'INSERT INTO empresa_tipos_obrigacao
                        (empresa_id, tipo_obrigacao_id, escritorio_id, responsavel_padrao_id)
                     VALUES (:empresa_id, :tipo_id, :escritorio_id, :responsavel)',
                    $parametros
                );
            } elseif ($atuais[$tipoId] !== $responsavel) {
                $this->executar(
                    'UPDATE empresa_tipos_obrigacao
                     SET responsavel_padrao_id = :responsavel
                     WHERE escritorio_id = :escritorio_id AND empresa_id = :empresa_id
                       AND tipo_obrigacao_id = :tipo_id',
                    $parametros
                );
            }
        }

        return $atuais;
    }
}
