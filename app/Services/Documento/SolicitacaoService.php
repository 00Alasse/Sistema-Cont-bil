<?php

declare(strict_types=1);

namespace App\Services\Documento;

use App\Core\Database;
use App\Core\UsuarioLogado;
use App\Exceptions\ConflitoException;
use App\Exceptions\NaoEncontradoException;
use App\Models\Documento\SolicitacaoDocumento;
use App\Models\Nucleo\LogAtividade;
use App\Services\Nucleo\AuditoriaService;
use Throwable;

/**
 * Operações do fluxo de documentos com histórico (Melhoria 2).
 *
 * Cada operação grava a mudança e o evento em logs_atividade na MESMA transação: se uma das duas
 * escritas falhar, nenhuma fica gravada. O log guarda só o necessário para o histórico (título,
 * prazo, nome original do arquivo, motivo da recusa); nunca o caminho do arquivo no disco.
 *
 * Transições permitidas (o estado de origem está no próprio UPDATE; 0 linhas = 409, sem evento):
 * aceitar e recusar só a partir de "enviada"; enviar ou reenviar só a partir de "aberta" ou "recusada".
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
     *
     * $desfazerArquivo (opcional) apaga o arquivo que ESTA requisição acabou de gravar no disco. Ele só é
     * chamado quando há certeza de que nada foi confirmado: a falha aconteceu antes do fim do callback e a
     * conexão não está mais em transação (o rollback terminou). Se o commit falhar, se houver uma transação
     * externa ou se o rollback não se confirmar, o arquivo é mantido. Uma falha ao apagar nunca substitui
     * a exceção original, que é sempre relançada.
     *
     * @param (callable(): mixed)|null $desfazerArquivo
     */
    public function anexarArquivo(
        UsuarioLogado $usuario,
        int $id,
        string $caminhoRelativo,
        string $nomeOriginal,
        string $enviadoEm,
        ?callable $desfazerArquivo = null
    ): void {
        $callbackConcluido = false;
        try {
            Database::transacao(function () use ($usuario, $id, $caminhoRelativo, $nomeOriginal, $enviadoEm, &$callbackConcluido): void {
                $atual = $this->existente($usuario, $id);
                $acao = empty($atual['arquivo_path']) ? 'enviar' : 'reenviar';

                $alteradas = $this->solicitacoes->anexarArquivo($usuario->escritorioId, $id, $caminhoRelativo, $nomeOriginal, $enviadoEm);
                $this->exigirTransicao($alteradas, 'Esta solicitação não aceita novos arquivos no estado atual.');
                $this->auditoria->registrar($usuario, self::ENTIDADE, $id, $acao, ['arquivo' => $nomeOriginal]);
                $callbackConcluido = true;
            });
        } catch (Throwable $erro) {
            if ($desfazerArquivo !== null && !$callbackConcluido && !Database::conexao()->inTransaction()) {
                try {
                    $desfazerArquivo();
                } catch (Throwable) {
                    // A limpeza é secundária: a exceção que importa (e que o Kernel registra) é a original.
                }
            }

            throw $erro;
        }
    }

    public function aceitar(UsuarioLogado $usuario, int $id): void
    {
        Database::transacao(function () use ($usuario, $id): void {
            $this->existente($usuario, $id);
            $alteradas = $this->solicitacoes->aceitar($usuario->escritorioId, $id);
            $this->exigirTransicao($alteradas, 'Só é possível aceitar um documento que está aguardando conferência.');
            $this->auditoria->registrar($usuario, self::ENTIDADE, $id, 'aceitar');
        });
    }

    public function recusar(UsuarioLogado $usuario, int $id, string $motivo): void
    {
        Database::transacao(function () use ($usuario, $id, $motivo): void {
            $this->existente($usuario, $id);
            $alteradas = $this->solicitacoes->recusar($usuario->escritorioId, $id, $motivo);
            $this->exigirTransicao($alteradas, 'Só é possível recusar um documento que está aguardando conferência.');
            $this->auditoria->registrar($usuario, self::ENTIDADE, $id, 'recusar', ['motivo' => $motivo]);
        });
    }

    /**
     * 0 linhas alteradas = o estado de origem não era o permitido (ou outra ação chegou antes).
     * A exceção desfaz a transação e impede o registro do evento.
     */
    private function exigirTransicao(int $alteradas, string $mensagem): void
    {
        if ($alteradas === 0) {
            throw new ConflitoException($mensagem);
        }
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
