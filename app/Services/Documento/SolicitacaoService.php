<?php

declare(strict_types=1);

namespace App\Services\Documento;

use App\Core\Database;
use App\Core\UsuarioLogado;
use App\Exceptions\NaoEncontradoException;
use App\Models\Documento\SolicitacaoDocumento;
use App\Models\Nucleo\LogAtividade;
use App\Services\Nucleo\AuditoriaService;

/**
 * Operações do fluxo de documentos com histórico (Melhoria 2).
 *
 * Cada operação grava a mudança e o evento em logs_atividade na MESMA transação: se uma das duas
 * escritas falhar, nenhuma fica gravada. O log guarda só o necessário para o histórico (título,
 * prazo, nome original do arquivo, motivo da recusa); nunca o caminho do arquivo no disco.
 */
final class SolicitacaoService
{
    public const ENTIDADE = 'solicitacao';

    public function __construct(
        private readonly SolicitacaoDocumento $solicitacoes,
        private readonly AuditoriaService $auditoria
    ) {
    }

    public static function padrao(): self
    {
        return new self(new SolicitacaoDocumento(), new AuditoriaService(new LogAtividade()));
    }

    /**
     * @param array{empresa_id: int, titulo: string, descricao?: ?string, prazo: string} $dados
     */
    public function criar(UsuarioLogado $usuario, array $dados): int
    {
        return Database::transacao(function () use ($usuario, $dados): int {
            $id = $this->solicitacoes->criar($usuario->escritorioId, $dados + ['criado_por' => $usuario->id]);
            $this->auditoria->registrar($usuario, self::ENTIDADE, $id, 'criar', [
                'titulo' => $dados['titulo'],
                'prazo' => $dados['prazo'],
            ]);

            return $id;
        });
    }

    /**
     * Envio do cliente. É "reenviar" quando já havia um arquivo (por exemplo, depois de uma recusa).
     */
    public function anexarArquivo(
        UsuarioLogado $usuario,
        int $id,
        string $caminhoRelativo,
        string $nomeOriginal,
        string $enviadoEm
    ): void {
        Database::transacao(function () use ($usuario, $id, $caminhoRelativo, $nomeOriginal, $enviadoEm): void {
            $atual = $this->existente($usuario, $id);
            $acao = empty($atual['arquivo_path']) ? 'enviar' : 'reenviar';

            $this->solicitacoes->anexarArquivo($usuario->escritorioId, $id, $caminhoRelativo, $nomeOriginal, $enviadoEm);
            $this->auditoria->registrar($usuario, self::ENTIDADE, $id, $acao, ['arquivo' => $nomeOriginal]);
        });
    }

    public function aceitar(UsuarioLogado $usuario, int $id): void
    {
        Database::transacao(function () use ($usuario, $id): void {
            $this->existente($usuario, $id);
            $this->solicitacoes->aceitar($usuario->escritorioId, $id);
            $this->auditoria->registrar($usuario, self::ENTIDADE, $id, 'aceitar');
        });
    }

    public function recusar(UsuarioLogado $usuario, int $id, string $motivo): void
    {
        Database::transacao(function () use ($usuario, $id, $motivo): void {
            $this->existente($usuario, $id);
            $this->solicitacoes->recusar($usuario->escritorioId, $id, $motivo);
            $this->auditoria->registrar($usuario, self::ENTIDADE, $id, 'recusar', ['motivo' => $motivo]);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function existente(UsuarioLogado $usuario, int $id): array
    {
        $solicitacao = $this->solicitacoes->buscar($usuario->escritorioId, $id);
        if ($solicitacao === null) {
            throw new NaoEncontradoException('Solicitação não encontrada.');
        }

        return $solicitacao;
    }
}
