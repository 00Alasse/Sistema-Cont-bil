<?php

declare(strict_types=1);

namespace App\Services\Fiscal;

use App\Core\Database;
use App\Core\UsuarioLogado;
use App\Exceptions\ConflitoException;
use App\Exceptions\NaoEncontradoException;
use App\Models\Cadastro\Empresa;
use App\Models\Fiscal\EmpresaTipoObrigacao;
use App\Models\Fiscal\TipoObrigacao;
use App\Models\Nucleo\LogAtividade;
use App\Services\Nucleo\AuditoriaService;
use InvalidArgumentException;
use PDOException;

/**
 * Regras do catálogo de tipos e dos vínculos com empresas. Toda escrita acontece numa
 * transação junto com a auditoria: se a auditoria falhar, nada fica gravado.
 */
final class TipoObrigacaoService
{
    private const ERRO_FK_EM_USO = 1451;

    public function __construct(
        private readonly TipoObrigacao $tipos,
        private readonly EmpresaTipoObrigacao $vinculos,
        private readonly Empresa $empresas,
        private readonly AuditoriaService $auditoria
    ) {
    }

    public static function padrao(): self
    {
        return new self(
            new TipoObrigacao(),
            new EmpresaTipoObrigacao(),
            new Empresa(),
            new AuditoriaService(new LogAtividade())
        );
    }

    public function nomeJaExiste(UsuarioLogado $usuario, string $nome, ?int $ignorarId = null): bool
    {
        return $this->tipos->nomeJaExiste($usuario->escritorioId, $nome, $ignorarId);
    }

    /**
     * @param array<string, mixed> $dados
     */
    public function criar(UsuarioLogado $usuario, array $dados): int
    {
        return Database::transacao(function () use ($usuario, $dados): int {
            $id = $this->tipos->criar($usuario->escritorioId, $dados);
            $this->auditoria->registrar($usuario, 'tipo_obrigacao', $id, 'criar');

            return $id;
        });
    }

    /**
     * @param array<string, mixed> $dados
     */
    public function atualizar(UsuarioLogado $usuario, int $id, array $dados): void
    {
        Database::transacao(function () use ($usuario, $id, $dados): void {
            $this->exigirTipo($usuario, $id);
            $this->tipos->atualizar($usuario->escritorioId, $id, $dados);
            $this->auditoria->registrar($usuario, 'tipo_obrigacao', $id, 'editar');
        });
    }

    public function excluir(UsuarioLogado $usuario, int $id): void
    {
        Database::transacao(function () use ($usuario, $id): void {
            $this->exigirTipo($usuario, $id);

            try {
                $this->tipos->excluir($usuario->escritorioId, $id);
            } catch (PDOException $erro) {
                if ((int) ($erro->errorInfo[1] ?? 0) === self::ERRO_FK_EM_USO) {
                    throw new ConflitoException(
                        'Existem obrigações deste tipo; inative o tipo em vez de excluí-lo.'
                    );
                }

                throw $erro;
            }

            $this->auditoria->registrar($usuario, 'tipo_obrigacao', $id, 'excluir');
        });
    }

    /**
     * Valida um envio do formulário de vínculos. Devolve erros por campo (vazio = válido).
     *
     * @param array<int, int|null> $vinculos tipo_obrigacao_id => responsavel_padrao_id
     * @return array<string, list<string>>
     */
    public function errosDosVinculos(UsuarioLogado $usuario, int $empresaId, array $vinculos): array
    {
        $erros = [];
        $disponiveis = $this->vinculos->idsDisponiveis($usuario->escritorioId, $empresaId);
        if (array_diff(array_keys($vinculos), $disponiveis) !== []) {
            $erros['tipos'] = ['Selecione apenas tipos ativos deste escritório.'];
        }

        foreach ($vinculos as $responsavel) {
            if ($responsavel !== null && !$this->empresas->responsavelValido($usuario->escritorioId, $responsavel)) {
                $erros['responsavel_padrao'] = ['Selecione um responsável ativo deste escritório.'];
                break;
            }
        }

        return $erros;
    }

    /**
     * Substitui o conjunto inteiro de vínculos da empresa numa única transação.
     *
     * Concorrência: o primeiro passo bloqueia a linha da empresa (FOR UPDATE). Uma segunda
     * substituição da mesma empresa espera a primeira terminar e só então lê os vínculos,
     * já com o resultado gravado. Sem isso, as duas leriam o mesmo conjunto inicial e a
     * segunda poderia deixar vínculos que ela mesma não pediu.
     *
     * @param array<int, int|null> $vinculos tipo_obrigacao_id => responsavel_padrao_id
     */
    public function substituirVinculos(UsuarioLogado $usuario, int $empresaId, array $vinculos): void
    {
        Database::transacao(function () use ($usuario, $empresaId, $vinculos): void {
            // Bloqueio e checagem do escritório na mesma consulta: empresa de outro escritório → 404.
            if (!$this->vinculos->bloquearEmpresa($usuario->escritorioId, $empresaId)) {
                throw new NaoEncontradoException();
            }

            // Defesa extra: o controller já valida, mas o service revalida depois do bloqueio.
            if ($this->errosDosVinculos($usuario, $empresaId, $vinculos) !== []) {
                throw new InvalidArgumentException('Os vínculos informados são inválidos.');
            }

            // "antes" é o que substituir() leu com bloqueio; "depois" é exatamente o que ele gravou.
            $antes = $this->vinculos->substituir($usuario->escritorioId, $empresaId, $vinculos);
            $this->auditoria->registrar($usuario, 'empresa', $empresaId, 'tipos_obrigacao', [
                'antes' => $this->vinculosParaAuditoria($antes),
                'depois' => $this->vinculosParaAuditoria($vinculos),
            ]);
        });
    }

    /**
     * Registra tipo e responsável de cada vínculo, para que a troca só do responsável
     * também apareça no histórico. Responsável nulo fica explícito como null.
     *
     * @param array<int, int|null> $vinculos tipo_obrigacao_id => responsavel_padrao_id
     * @return list<array{tipo_obrigacao_id: int, responsavel_padrao_id: int|null}>
     */
    private function vinculosParaAuditoria(array $vinculos): array
    {
        ksort($vinculos);
        $lista = [];
        foreach ($vinculos as $tipoId => $responsavelId) {
            $lista[] = ['tipo_obrigacao_id' => $tipoId, 'responsavel_padrao_id' => $responsavelId];
        }

        return $lista;
    }

    private function exigirTipo(UsuarioLogado $usuario, int $id): void
    {
        if ($this->tipos->buscar($usuario->escritorioId, $id) === null) {
            throw new NaoEncontradoException();
        }
    }
}
