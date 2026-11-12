<?php

declare(strict_types=1);

namespace Tests\Support;

use PDO;

final class FabricaCadastro
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function criarEscritorio(): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO escritorios (nome, email) VALUES (:nome, :email)'
        );
        $stmt->execute([
            'nome' => 'Escritório C01 ' . bin2hex(random_bytes(4)),
            'email' => 'c01-' . bin2hex(random_bytes(6)) . '@exemplo.test',
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function criarUsuario(
        int $escritorioId,
        string $perfil = 'ADMIN',
        ?int $empresaId = null,
        bool $ativo = true
    ): int {
        $stmt = $this->pdo->prepare(
            'INSERT INTO usuarios
                (escritorio_id, empresa_id, nome, email, senha_hash, perfil, ativo)
             VALUES
                (:escritorio_id, :empresa_id, :nome, :email, :senha_hash, :perfil, :ativo)'
        );
        $stmt->execute([
            'escritorio_id' => $escritorioId,
            'empresa_id' => $empresaId,
            'nome' => 'Usuário C01 de teste',
            'email' => 'c01-' . bin2hex(random_bytes(8)) . '@exemplo.test',
            'senha_hash' => password_hash('Senha@123', PASSWORD_DEFAULT),
            'perfil' => $perfil,
            'ativo' => $ativo ? 1 : 0,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param array<string, mixed> $opcoes
     */
    public function criarEmpresa(
        int $escritorioId,
        string $cnpj,
        string $razaoSocial,
        array $opcoes = []
    ): int {
        $dados = array_merge([
            'nome_fantasia' => null,
            'regime_tributario' => 'SIMPLES_NACIONAL',
            'segmento' => null,
            'contato_nome' => null,
            'email' => null,
            'telefone' => null,
            'responsavel_id' => null,
            'bpo_ativo' => 0,
            'consultivo_ativo' => 0,
            'status' => 'ATIVA',
            'observacoes' => null,
        ], $opcoes);

        $stmt = $this->pdo->prepare(
            'INSERT INTO empresas (
                escritorio_id, razao_social, nome_fantasia, cnpj, regime_tributario,
                segmento, contato_nome, email, telefone, responsavel_id, bpo_ativo,
                consultivo_ativo, status, observacoes
             ) VALUES (
                :escritorio_id, :razao_social, :nome_fantasia, :cnpj, :regime_tributario,
                :segmento, :contato_nome, :email, :telefone, :responsavel_id, :bpo_ativo,
                :consultivo_ativo, :status, :observacoes
             )'
        );
        $stmt->execute([
            'escritorio_id' => $escritorioId,
            'razao_social' => $razaoSocial,
            'nome_fantasia' => $dados['nome_fantasia'],
            'cnpj' => $cnpj,
            'regime_tributario' => $dados['regime_tributario'],
            'segmento' => $dados['segmento'],
            'contato_nome' => $dados['contato_nome'],
            'email' => $dados['email'],
            'telefone' => $dados['telefone'],
            'responsavel_id' => $dados['responsavel_id'],
            'bpo_ativo' => $dados['bpo_ativo'],
            'consultivo_ativo' => $dados['consultivo_ativo'],
            'status' => $dados['status'],
            'observacoes' => $dados['observacoes'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }
}
