<?php

declare(strict_types=1);

namespace App\Models\Nucleo;

use App\Core\Model;

/**
 * Tentativas de login. A tabela não tem escritorio_id: no login o escritório ainda é desconhecido
 * e a contagem é por e-mail e por IP.
 */
final class TentativaLogin extends Model
{
    public function registrar(string $email, string $ip, bool $sucesso, string $quando): void
    {
        $this->inserir(
            'INSERT INTO tentativas_login (email, ip, sucesso, criado_em)
             VALUES (:email, :ip, :sucesso, :criado_em)',
            ['email' => $email, 'ip' => $ip, 'sucesso' => $sucesso ? 1 : 0, 'criado_em' => $quando]
        );
    }

    /**
     * Conta as falhas desde o instante informado, ignorando as anteriores ao último login
     * bem-sucedido do mesmo e-mail (quem acertou a senha começa do zero).
     */
    public function contarFalhasPorEmail(string $email, string $desde): int
    {
        $total = $this->buscarUm(
            'SELECT COUNT(*) AS total FROM tentativas_login
             WHERE email = :email AND sucesso = 0 AND criado_em >= :desde
               AND id > COALESCE(
                   (SELECT MAX(ok.id) FROM tentativas_login ok WHERE ok.email = :email_ok AND ok.sucesso = 1),
                   0
               )',
            ['email' => $email, 'email_ok' => $email, 'desde' => $desde]
        );

        return (int) ($total['total'] ?? 0);
    }

    public function contarFalhasPorIp(string $ip, string $desde): int
    {
        $total = $this->buscarUm(
            'SELECT COUNT(*) AS total FROM tentativas_login
             WHERE ip = :ip AND sucesso = 0 AND criado_em >= :desde',
            ['ip' => $ip, 'desde' => $desde]
        );

        return (int) ($total['total'] ?? 0);
    }
}
