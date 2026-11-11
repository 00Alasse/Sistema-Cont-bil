<?php

declare(strict_types=1);

namespace App\Models\Nucleo;

use App\Core\Model;

final class LogAtividade extends Model
{
    public function registrar(
        int $escritorioId,
        int $usuarioId,
        string $entidade,
        int $entidadeId,
        string $acao,
        string $detalhesJson,
        ?string $ip
    ): void {
        $this->inserir(
            'INSERT INTO logs_atividade
                (escritorio_id, usuario_id, entidade, entidade_id, acao, detalhes, ip)
             VALUES
                (:escritorio_id, :usuario_id, :entidade, :entidade_id, :acao, :detalhes, :ip)',
            [
                'escritorio_id' => $escritorioId,
                'usuario_id' => $usuarioId,
                'entidade' => $entidade,
                'entidade_id' => $entidadeId,
                'acao' => $acao,
                'detalhes' => $detalhesJson,
                'ip' => $ip,
            ]
        );
    }
}
