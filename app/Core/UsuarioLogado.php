<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Contexto imutável do usuário autenticado. É montado pelo servidor a cada requisição
 * (nunca vem do cliente) e é a única fonte do escritório e do perfil.
 */
final class UsuarioLogado
{
    /**
     * @param list<string> $modulos códigos dos módulos contratados e ativos do escritório
     */
    public function __construct(
        public readonly int $id,
        public readonly int $escritorioId,
        public readonly string $perfil,
        public readonly ?int $empresaId,
        public readonly string $nome,
        public readonly array $modulos
    ) {
    }

    public function ehAdmin(): bool
    {
        return $this->perfil === 'ADMIN';
    }

    public function ehCliente(): bool
    {
        return $this->perfil === 'CLIENTE';
    }

    public function temModulo(string $codigo): bool
    {
        return in_array($codigo, $this->modulos, true);
    }
}
