<?php

declare(strict_types=1);

namespace Tests\Support;

use PDO;

/**
 * Cria registros fiscais válidos para testes. Escritório, usuário e empresa
 * continuam vindo da FabricaCadastro.
 */
final class FabricaFiscal
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @param array<string, mixed> $opcoes
     */
    public function criarTipo(int $escritorioId, array $opcoes = []): int
    {
        $dados = array_merge([
            // Nome aleatório: a chave única é (escritorio_id, nome).
            'nome' => 'Tipo de teste ' . bin2hex(random_bytes(4)),
            'descricao' => null,
            'esfera' => 'FEDERAL',
            'periodicidade' => 'MENSAL',
            'dia_vencimento' => 20,
            'meses_apos_competencia' => 1,
            'ativo' => 1,
        ], $opcoes);

        $stmt = $this->pdo->prepare(
            'INSERT INTO tipos_obrigacao
                (escritorio_id, nome, descricao, esfera, periodicidade,
                 dia_vencimento, meses_apos_competencia, ativo)
             VALUES
                (:escritorio_id, :nome, :descricao, :esfera, :periodicidade,
                 :dia_vencimento, :meses_apos_competencia, :ativo)'
        );
        $stmt->execute([
            'escritorio_id' => $escritorioId,
            'nome' => $dados['nome'],
            'descricao' => $dados['descricao'],
            'esfera' => $dados['esfera'],
            'periodicidade' => $dados['periodicidade'],
            'dia_vencimento' => $dados['dia_vencimento'],
            'meses_apos_competencia' => $dados['meses_apos_competencia'],
            'ativo' => $dados['ativo'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function criarVinculo(
        int $escritorioId,
        int $empresaId,
        int $tipoId,
        ?int $responsavelPadraoId = null
    ): void {
        $stmt = $this->pdo->prepare(
            'INSERT INTO empresa_tipos_obrigacao
                (empresa_id, tipo_obrigacao_id, escritorio_id, responsavel_padrao_id)
             VALUES
                (:empresa_id, :tipo_obrigacao_id, :escritorio_id, :responsavel_padrao_id)'
        );
        $stmt->execute([
            'empresa_id' => $empresaId,
            'tipo_obrigacao_id' => $tipoId,
            'escritorio_id' => $escritorioId,
            'responsavel_padrao_id' => $responsavelPadraoId,
        ]);
    }

    /**
     * Os padrões são consistentes com os CHECKs: competência no dia 01, vencimento
     * posterior e, se o status for ENTREGUE, entrega e autor preenchidos.
     *
     * @param array<string, mixed> $opcoes
     */
    public function criarObrigacao(
        int $escritorioId,
        int $empresaId,
        int $tipoId,
        int $criadoPor,
        array $opcoes = []
    ): int {
        $dados = array_merge([
            'competencia' => '2026-01-01',
            'vencimento' => '2026-02-20',
            'status' => 'PENDENTE',
            'responsavel_id' => null,
            'entregue_em' => null,
            'entregue_por' => null,
            'protocolo' => null,
            'observacao' => null,
        ], $opcoes);

        if ($dados['status'] === 'ENTREGUE') {
            $dados['entregue_em'] ??= $dados['vencimento'] . ' 10:00:00';
            $dados['entregue_por'] ??= $criadoPor;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO obrigacoes
                (escritorio_id, empresa_id, tipo_obrigacao_id, competencia, vencimento,
                 status, responsavel_id, entregue_em, entregue_por, protocolo,
                 observacao, criado_por)
             VALUES
                (:escritorio_id, :empresa_id, :tipo_obrigacao_id, :competencia, :vencimento,
                 :status, :responsavel_id, :entregue_em, :entregue_por, :protocolo,
                 :observacao, :criado_por)'
        );
        $stmt->execute([
            'escritorio_id' => $escritorioId,
            'empresa_id' => $empresaId,
            'tipo_obrigacao_id' => $tipoId,
            'competencia' => $dados['competencia'],
            'vencimento' => $dados['vencimento'],
            'status' => $dados['status'],
            'responsavel_id' => $dados['responsavel_id'],
            'entregue_em' => $dados['entregue_em'],
            'entregue_por' => $dados['entregue_por'],
            'protocolo' => $dados['protocolo'],
            'observacao' => $dados['observacao'],
            'criado_por' => $criadoPor,
        ]);

        return (int) $this->pdo->lastInsertId();
    }
}
