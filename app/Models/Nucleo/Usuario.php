<?php

declare(strict_types=1);

namespace App\Models\Nucleo;

use App\Core\Model;

final class Usuario extends Model
{
    /**
     * Exceção consciente à regra do escritorioId como primeiro parâmetro: no login ainda não
     * sabemos o escritório, e o e-mail é único em todo o sistema (uq_usuarios_email).
     *
     * @return array<string, mixed>|null
     */
    public function buscarPorEmailParaLogin(string $email): ?array
    {
        return $this->buscarUm(
            'SELECT u.id, u.escritorio_id, u.empresa_id, u.nome, u.perfil, u.senha_hash,
                    u.ativo AS usuario_ativo, e.ativo AS escritorio_ativo
             FROM usuarios u
             INNER JOIN escritorios e ON e.id = u.escritorio_id
             WHERE u.email = :email',
            ['email' => $email]
        );
    }

    /**
     * Estado atual do usuário, usado a cada requisição para que a desativação valha de imediato.
     *
     * @return array<string, mixed>|null
     */
    public function buscarAtivo(int $escritorioId, int $id): ?array
    {
        return $this->buscarUm(
            'SELECT u.id, u.escritorio_id, u.empresa_id, u.nome, u.perfil
             FROM usuarios u
             INNER JOIN escritorios e ON e.id = u.escritorio_id
             WHERE u.id = :id AND u.escritorio_id = :escritorio_id
               AND u.ativo = 1 AND e.ativo = 1',
            ['id' => $id, 'escritorio_id' => $escritorioId]
        );
    }

    /**
     * @return list<string>
     */
    public function modulosAtivos(int $escritorioId): array
    {
        $linhas = $this->buscarTodos(
            'SELECT modulo FROM escritorio_modulos
             WHERE escritorio_id = :escritorio_id AND ativo = 1
             ORDER BY modulo',
            ['escritorio_id' => $escritorioId]
        );

        return array_map(static fn (array $linha): string => (string) $linha['modulo'], $linhas);
    }

    public function atualizarHash(int $escritorioId, int $id, string $hash): void
    {
        $this->executar(
            'UPDATE usuarios SET senha_hash = :hash
             WHERE id = :id AND escritorio_id = :escritorio_id',
            ['hash' => $hash, 'id' => $id, 'escritorio_id' => $escritorioId]
        );
    }

    public function registrarLogin(int $escritorioId, int $id, string $quando): void
    {
        $this->executar(
            'UPDATE usuarios SET ultimo_login_em = :quando
             WHERE id = :id AND escritorio_id = :escritorio_id',
            ['quando' => $quando, 'id' => $id, 'escritorio_id' => $escritorioId]
        );
    }
}
