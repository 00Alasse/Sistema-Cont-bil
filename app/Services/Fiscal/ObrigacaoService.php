<?php

declare(strict_types=1);

namespace App\Services\Fiscal;

use App\Core\Database;
use App\Core\UsuarioLogado;
use App\Exceptions\ConflitoException;
use App\Exceptions\NaoEncontradoException;
use App\Models\Cadastro\Empresa;
use App\Models\Fiscal\Obrigacao;
use App\Models\Fiscal\TipoObrigacao;
use App\Models\Nucleo\LogAtividade;
use App\Services\Nucleo\AuditoriaService;
use App\Support\Competencia;
use PDOException;

/**
 * Cadastro manual de obrigações. Cada escrita acontece numa transação junto com a
 * auditoria (entidade "obrigacao"): se a auditoria falhar, nada fica gravado.
 * Mudança de status não é feita aqui (FI04).
 */
final class ObrigacaoService
{
    private const ERRO_FK_EM_USO = 1451;

    public const MENSAGEM_DUPLICADA = 'Já existe obrigação deste tipo nesta competência para esta empresa.';

    public function __construct(
        private readonly Obrigacao $obrigacoes,
        private readonly Empresa $empresas,
        private readonly TipoObrigacao $tipos,
        private readonly CalculadoraVencimento $calculadora,
        private readonly AuditoriaService $auditoria
    ) {
    }

    public static function padrao(): self
    {
        return new self(
            new Obrigacao(),
            new Empresa(),
            new TipoObrigacao(),
            new CalculadoraVencimento(),
            new AuditoriaService(new LogAtividade())
        );
    }

    /**
     * Confere no banco o que o Validator não alcança: empresa e tipo do escritório (tipo ativo),
     * responsável ativo e duplicidade. Devolve erros por campo (vazio = válido).
     *
     * @param array{empresa_id: int, tipo_obrigacao_id: int, competencia: string, responsavel_id: int|null} $dados
     * @return array<string, list<string>>
     */
    public function errosDaCriacao(UsuarioLogado $usuario, array $dados): array
    {
        $erros = [];
        if ($this->empresas->buscar($usuario->escritorioId, $dados['empresa_id']) === null) {
            $erros['empresa_id'] = ['Selecione uma empresa deste escritório.'];
        }

        $tipo = $this->tipos->buscar($usuario->escritorioId, $dados['tipo_obrigacao_id']);
        if ($tipo === null || (int) $tipo['ativo'] !== 1) {
            $erros['tipo_obrigacao_id'] = ['Selecione um tipo de obrigação ativo deste escritório.'];
        }

        $erros += $this->errosDoResponsavel($usuario, $dados['responsavel_id']);

        if ($erros === [] && $this->obrigacoes->jaExiste(
            $usuario->escritorioId,
            $dados['empresa_id'],
            $dados['tipo_obrigacao_id'],
            $dados['competencia']
        )) {
            $erros['competencia'] = [self::MENSAGEM_DUPLICADA];
        }

        return $erros;
    }

    /**
     * Na edição, manter o responsável atual é sempre permitido, mesmo que ele tenha sido
     * desativado depois: só uma troca de responsável precisa ser validada.
     *
     * @return array<string, list<string>>
     */
    public function errosDoResponsavel(UsuarioLogado $usuario, ?int $responsavelId, ?int $responsavelAtual = null): array
    {
        if ($responsavelId === null || $responsavelId === $responsavelAtual) {
            return [];
        }

        return $this->empresas->responsavelValido($usuario->escritorioId, $responsavelId)
            ? []
            : ['responsavel_id' => ['Selecione um responsável ativo deste escritório.']];
    }

    /**
     * Vencimento sugerido pela fórmula do tipo (ARCHITECTURE §10.2), em "AAAA-MM-DD".
     * Null quando o tipo não existe, está inativo ou não tem dia fixo (anual/eventual sem dia).
     */
    public function sugerirVencimento(UsuarioLogado $usuario, int $tipoId, Competencia $competencia): ?string
    {
        $tipo = $this->tipos->buscar($usuario->escritorioId, $tipoId);
        if ($tipo === null || (int) $tipo['ativo'] !== 1 || $tipo['dia_vencimento'] === null) {
            return null;
        }

        return $this->calculadora
            ->calcular($competencia, (int) $tipo['dia_vencimento'], (int) $tipo['meses_apos_competencia'])
            ->format('Y-m-d');
    }

    /**
     * @param array<string, mixed> $dados
     */
    public function criar(UsuarioLogado $usuario, array $dados): int
    {
        return Database::transacao(function () use ($usuario, $dados): int {
            // criado_por vem da sessão, nunca do formulário.
            $id = $this->obrigacoes->criar($usuario->escritorioId, ['criado_por' => $usuario->id] + $dados);
            $this->auditoria->registrar($usuario, 'obrigacao', $id, 'criar');

            return $id;
        });
    }

    /**
     * @param array<string, mixed> $dados
     */
    public function atualizar(UsuarioLogado $usuario, int $id, array $dados, int $versao): void
    {
        Database::transacao(function () use ($usuario, $id, $dados, $versao): void {
            $this->exigirObrigacao($usuario, $id);
            $this->obrigacoes->atualizar($usuario->escritorioId, $id, $dados, $versao);
            $this->auditoria->registrar($usuario, 'obrigacao', $id, 'editar');
        });
    }

    public function excluir(UsuarioLogado $usuario, int $id): void
    {
        Database::transacao(function () use ($usuario, $id): void {
            $this->exigirObrigacao($usuario, $id);

            try {
                $this->obrigacoes->excluir($usuario->escritorioId, $id);
            } catch (PDOException $erro) {
                // Hoje nada referencia obrigacoes; as solicitações de documento (DO01) vão referenciar.
                if ((int) ($erro->errorInfo[1] ?? 0) === self::ERRO_FK_EM_USO) {
                    throw new ConflitoException('A obrigação possui solicitações vinculadas e não pode ser excluída.');
                }

                throw $erro;
            }

            $this->auditoria->registrar($usuario, 'obrigacao', $id, 'excluir');
        });
    }

    private function exigirObrigacao(UsuarioLogado $usuario, int $id): void
    {
        if ($this->obrigacoes->buscar($usuario->escritorioId, $id) === null) {
            throw new NaoEncontradoException();
        }
    }
}
