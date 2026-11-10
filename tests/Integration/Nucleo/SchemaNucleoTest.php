<?php

declare(strict_types=1);

namespace Tests\Integration\Nucleo;

use PDOException;
use Tests\Integration\IntegrationTestCase;

final class SchemaNucleoTest extends IntegrationTestCase
{
    public function testRejeitaClienteComEmpresaDeOutroEscritorio(): void
    {
        [$escritorioA, $escritorioB] = $this->criarEscritorios();
        $empresaB = $this->criarEmpresa($escritorioB, '10000001000100');

        $this->expectException(PDOException::class);
        $this->criarUsuario($escritorioA, 'cliente@exemplo.test', 'CLIENTE', $empresaB);
    }

    public function testRejeitaResponsavelDeOutroEscritorio(): void
    {
        [$escritorioA, $escritorioB] = $this->criarEscritorios();
        $responsavelB = $this->criarUsuario($escritorioB, 'responsavel@modelo.test', 'COLABORADOR');

        $this->expectException(PDOException::class);
        $this->criarEmpresa($escritorioA, '10000002000100', $responsavelB);
    }

    public function testRejeitaClienteSemEmpresa(): void
    {
        [$escritorio] = $this->criarEscritorios();

        $this->expectException(PDOException::class);
        $this->criarUsuario($escritorio, 'cliente@exemplo.test', 'CLIENTE');
    }

    public function testRejeitaEmailDuplicado(): void
    {
        [$escritorio] = $this->criarEscritorios();
        $this->criarUsuario($escritorio, 'admin@exemplo.test', 'ADMIN');

        $this->expectException(PDOException::class);
        $this->criarUsuario($escritorio, 'admin@exemplo.test', 'COLABORADOR');
    }

    public function testCnpjEhUnicoDentroDoEscritorioMasPodeExistirEmOutro(): void
    {
        [$escritorioA, $escritorioB] = $this->criarEscritorios();
        $cnpj = '10000003000100';

        $this->criarEmpresa($escritorioA, $cnpj);
        $this->criarEmpresa($escritorioB, $cnpj);

        $this->expectException(PDOException::class);
        $this->criarEmpresa($escritorioA, $cnpj);
    }

    /**
     * @return array{int, int}
     */
    private function criarEscritorios(): array
    {
        $stmt = $this->conexao()->prepare(
            'INSERT INTO escritorios (nome, email) VALUES (:nome, :email)'
        );
        $stmt->execute(['nome' => 'Escritório A', 'email' => 'a@exemplo.test']);
        $escritorioA = (int) $this->conexao()->lastInsertId();
        $stmt->execute(['nome' => 'Escritório B', 'email' => 'b@exemplo.test']);

        return [$escritorioA, (int) $this->conexao()->lastInsertId()];
    }

    private function criarEmpresa(int $escritorioId, string $cnpj, ?int $responsavelId = null): int
    {
        $stmt = $this->conexao()->prepare(
            'INSERT INTO empresas (escritorio_id, razao_social, cnpj, regime_tributario, responsavel_id)
             VALUES (:escritorio_id, :razao_social, :cnpj, :regime, :responsavel_id)'
        );
        $stmt->execute([
            'escritorio_id' => $escritorioId,
            'razao_social' => 'Empresa de teste',
            'cnpj' => $cnpj,
            'regime' => 'SIMPLES_NACIONAL',
            'responsavel_id' => $responsavelId,
        ]);

        return (int) $this->conexao()->lastInsertId();
    }

    private function criarUsuario(
        int $escritorioId,
        string $email,
        string $perfil,
        ?int $empresaId = null
    ): int {
        $stmt = $this->conexao()->prepare(
            'INSERT INTO usuarios (escritorio_id, empresa_id, nome, email, senha_hash, perfil)
             VALUES (:escritorio_id, :empresa_id, :nome, :email, :senha_hash, :perfil)'
        );
        $stmt->execute([
            'escritorio_id' => $escritorioId,
            'empresa_id' => $empresaId,
            'nome' => 'Usuário de teste',
            'email' => $email,
            'senha_hash' => password_hash('teste-local', PASSWORD_DEFAULT),
            'perfil' => $perfil,
        ]);

        return (int) $this->conexao()->lastInsertId();
    }
}
