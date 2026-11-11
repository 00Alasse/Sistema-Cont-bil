<?php

declare(strict_types=1);

namespace App\Services\Nucleo;

use App\Core\Config;
use App\Core\Request;
use App\Core\UsuarioLogado;
use App\Models\Nucleo\LogAtividade;
use InvalidArgumentException;
use RuntimeException;

final class AuditoriaService
{
    public function __construct(private readonly LogAtividade $logAtividade)
    {
    }

    /**
     * @param array<string, mixed> $detalhes
     */
    public function registrar(
        UsuarioLogado $u,
        string $entidade,
        int $id,
        string $acao,
        array $detalhes = []
    ): void {
        $dadosUsuario = get_object_vars($u);
        $usuarioId = $dadosUsuario['id'] ?? null;
        $escritorioId = $dadosUsuario['escritorioId'] ?? null;
        if (!is_int($usuarioId) || !is_int($escritorioId) || $usuarioId < 1 || $escritorioId < 1) {
            throw new RuntimeException('O contexto do usuário não contém identificadores válidos.');
        }

        $detalhesSeguros = $this->removerSegredos($detalhes);
        $detalhesJson = json_encode(
            $detalhesSeguros,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        $request = Request::capturar((string) Config::obter('app.base_path', ''));

        $this->logAtividade->registrar(
            $escritorioId,
            $usuarioId,
            $entidade,
            $id,
            $acao,
            $detalhesJson,
            $request->ip()
        );
    }

    /**
     * @param array<array-key, mixed> $detalhes
     * @return array<array-key, mixed>
     */
    private function removerSegredos(array $detalhes): array
    {
        $seguros = [];
        foreach ($detalhes as $chave => $valor) {
            if (is_string($chave) && preg_match(
                '/senha|password|passphrase|token|secret|credential|credencia(?:l|is)|authorization|cookie|'
                . 'api[_-]?key|chave[_-]?api|bearer/i',
                $chave
            ) === 1) {
                continue;
            }

            if (is_array($valor)) {
                $seguros[$chave] = $this->removerSegredos($valor);
            } elseif (is_scalar($valor) || $valor === null) {
                $seguros[$chave] = $valor;
            } else {
                throw new InvalidArgumentException('Os detalhes da auditoria devem conter valores simples.');
            }
        }

        return $seguros;
    }
}
