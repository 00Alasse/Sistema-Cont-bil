<?php

declare(strict_types=1);

namespace App\Models\Fiscal;

use App\Core\Model;
use App\Core\Pagina;
use App\Core\Paginacao;
use App\Core\QueryFiltros;
use App\Exceptions\ConflitoException;
use App\Support\Competencia;
use App\Support\Relogio;
use App\Support\RelogioSistema;
use DateTimeImmutable;
use InvalidArgumentException;
use PDO;

/**
 * Obrigações do escritório. As situações (atrasada, vence hoje...) nunca são gravadas:
 * são calculadas no SQL com o "hoje" do Relogio (ARCHITECTURE §10.1).
 */
final class Obrigacao extends Model
{
    public const STATUS = ['PENDENTE', 'EM_ANDAMENTO', 'AGUARDANDO_CLIENTE', 'ENTREGUE', 'DISPENSADA'];

    public const SITUACOES = ['atrasada', 'vence_hoje', 'vence_7d', 'entregue_atraso'];

    private const ABERTA = "o.status IN ('PENDENTE', 'EM_ANDAMENTO', 'AGUARDANDO_CLIENTE')";

    private const ATRASADA = self::ABERTA . ' AND o.vencimento < ref.hoje';

    private const ENTREGUE_COM_ATRASO = "o.status = 'ENTREGUE' AND DATE(o.entregue_em) > o.vencimento";

    /**
     * "hoje" entra uma única vez, numa tabela derivada de uma linha (ref). Assim a listagem e
     * a contagem usam o mesmo parâmetro, e as condições leem ref.hoje quantas vezes precisarem.
     */
    private const FROM = 'FROM obrigacoes o
        JOIN empresas e ON e.id = o.empresa_id AND e.escritorio_id = o.escritorio_id
        JOIN tipos_obrigacao t ON t.id = o.tipo_obrigacao_id AND t.escritorio_id = o.escritorio_id
        LEFT JOIN usuarios u ON u.id = o.responsavel_id AND u.escritorio_id = o.escritorio_id
        CROSS JOIN (SELECT CAST(:hoje AS DATE) AS hoje) ref';

    private const COLUNAS = 'SELECT o.*, e.razao_social AS empresa_nome, t.nome AS tipo_nome,
        u.nome AS responsavel_nome,
        (' . self::ATRASADA . ') AS atrasada,
        (' . self::ENTREGUE_COM_ATRASO . ') AS entregue_com_atraso ';

    private readonly Relogio $relogio;

    public function __construct(?Relogio $relogio = null, ?PDO $db = null)
    {
        parent::__construct($db);
        $this->relogio = $relogio ?? new RelogioSistema();
    }

    /**
     * Filtros aceitos (API §4.2): q, empresa_id, tipo_id, status, situacao, responsavel (ID),
     * competencia (AAAA-MM), de, ate e ordem. Valores inválidos são ignorados.
     *
     * @param array<string, mixed> $filtros
     */
    public function listar(int $escritorioId, array $filtros, Paginacao $paginacao): Pagina
    {
        [$condicoes, $parametros] = $this->condicoes($escritorioId, $filtros);
        $where = implode(' AND ', $condicoes);
        $ordenacao = $this->ordenacao($filtros['ordem'] ?? null);

        return $this->paginar(
            self::COLUNAS . self::FROM . " WHERE {$where} ORDER BY {$ordenacao}, o.id ASC",
            'SELECT COUNT(*) ' . self::FROM . " WHERE {$where}",
            $parametros,
            $paginacao
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function buscar(int $escritorioId, int $id): ?array
    {
        return $this->buscarUm(
            self::COLUNAS . self::FROM . ' WHERE o.escritorio_id = :escritorio_id AND o.id = :id',
            ['hoje' => $this->hoje(), 'escritorio_id' => $escritorioId, 'id' => $id]
        );
    }

    /**
     * O status nasce PENDENTE (padrão da tabela); mudanças de status são da FI04.
     *
     * @param array<string, mixed> $dados
     */
    public function criar(int $escritorioId, array $dados): int
    {
        return $this->inserir(
            'INSERT INTO obrigacoes (
                escritorio_id, empresa_id, tipo_obrigacao_id, competencia, vencimento,
                responsavel_id, observacao, criado_por
             ) VALUES (
                :escritorio_id, :empresa_id, :tipo_obrigacao_id, :competencia, :vencimento,
                :responsavel_id, :observacao, :criado_por
             )',
            [
                'escritorio_id' => $escritorioId,
                'empresa_id' => $dados['empresa_id'],
                'tipo_obrigacao_id' => $dados['tipo_obrigacao_id'],
                'competencia' => $dados['competencia'],
                'vencimento' => $dados['vencimento'],
                'responsavel_id' => $dados['responsavel_id'],
                'observacao' => $dados['observacao'],
                'criado_por' => $dados['criado_por'],
            ]
        );
    }

    /**
     * Só vencimento, responsável e observação mudam (API §4.2). Versão diferente → 409.
     *
     * @param array<string, mixed> $dados
     */
    public function atualizar(int $escritorioId, int $id, array $dados, int $versao): void
    {
        $afetadas = $this->executar(
            'UPDATE obrigacoes
             SET vencimento = :vencimento,
                 responsavel_id = :responsavel_id,
                 observacao = :observacao,
                 versao = versao + 1
             WHERE escritorio_id = :escritorio_id AND id = :id AND versao = :versao',
            [
                'vencimento' => $dados['vencimento'],
                'responsavel_id' => $dados['responsavel_id'],
                'observacao' => $dados['observacao'],
                'escritorio_id' => $escritorioId,
                'id' => $id,
                'versao' => $versao,
            ]
        );
        if ($afetadas !== 1) {
            throw new ConflitoException();
        }
    }

    /**
     * Grava a mudança de status (FI04). As regras ficam no ObrigacaoStatusService; aqui só o SQL.
     * Protocolo nulo mantém o que já estava gravado (D4/D9). Versão diferente → 409.
     *
     * @param array{status: string, entregue_em: string|null, entregue_por: int|null, protocolo: string|null} $dados
     */
    public function mudarStatus(int $escritorioId, int $id, array $dados, int $versao): void
    {
        $afetadas = $this->executar(
            'UPDATE obrigacoes
             SET status = :status,
                 entregue_em = :entregue_em,
                 entregue_por = :entregue_por,
                 protocolo = COALESCE(:protocolo, protocolo),
                 versao = versao + 1
             WHERE escritorio_id = :escritorio_id AND id = :id AND versao = :versao',
            [
                'status' => $dados['status'],
                'entregue_em' => $dados['entregue_em'],
                'entregue_por' => $dados['entregue_por'],
                'protocolo' => $dados['protocolo'],
                'escritorio_id' => $escritorioId,
                'id' => $id,
                'versao' => $versao,
            ]
        );
        if ($afetadas !== 1) {
            throw new ConflitoException();
        }
    }

    /**
     * Histórico de status lido da auditoria (logs_atividade), do mais recente para o mais antigo.
     * Só eventos "status:*" desta obrigação e deste escritório (D2). O id desempata eventos
     * gravados no mesmo segundo.
     *
     * @return list<array{id: int, criado_em: string, usuario_nome: string|null, de: string|null, para: string|null, justificativa: string|null, protocolo: string|null}>
     */
    public function historico(int $escritorioId, int $id): array
    {
        $linhas = $this->buscarTodos(
            "SELECT l.id, l.criado_em, l.detalhes, u.nome AS usuario_nome
             FROM logs_atividade l
             LEFT JOIN usuarios u ON u.id = l.usuario_id AND u.escritorio_id = l.escritorio_id
             WHERE l.escritorio_id = :escritorio_id
               AND l.entidade = 'obrigacao'
               AND l.entidade_id = :id
               AND l.acao LIKE 'status:%'
             ORDER BY l.criado_em DESC, l.id DESC",
            ['escritorio_id' => $escritorioId, 'id' => $id]
        );

        $historico = [];
        foreach ($linhas as $linha) {
            $detalhes = json_decode((string) ($linha['detalhes'] ?? ''), true);
            $detalhes = is_array($detalhes) ? $detalhes : [];
            $historico[] = [
                'id' => (int) $linha['id'],
                'criado_em' => (string) $linha['criado_em'],
                'usuario_nome' => is_string($linha['usuario_nome'] ?? null) ? $linha['usuario_nome'] : null,
                'de' => self::textoOuNulo($detalhes['de'] ?? null),
                'para' => self::textoOuNulo($detalhes['para'] ?? null),
                'justificativa' => self::textoOuNulo($detalhes['justificativa'] ?? null),
                'protocolo' => self::textoOuNulo($detalhes['protocolo'] ?? null),
            ];
        }

        return $historico;
    }

    public function excluir(int $escritorioId, int $id): int
    {
        return $this->executar(
            'DELETE FROM obrigacoes WHERE escritorio_id = :escritorio_id AND id = :id',
            ['escritorio_id' => $escritorioId, 'id' => $id]
        );
    }

    /**
     * Espelha a chave única uq_obr_competencia (empresa + tipo + competência).
     */
    public function jaExiste(int $escritorioId, int $empresaId, int $tipoId, string $competencia): bool
    {
        return $this->buscarUm(
            'SELECT 1 FROM obrigacoes
             WHERE escritorio_id = :escritorio_id AND empresa_id = :empresa_id
               AND tipo_obrigacao_id = :tipo_id AND competencia = :competencia
             LIMIT 1',
            [
                'escritorio_id' => $escritorioId,
                'empresa_id' => $empresaId,
                'tipo_id' => $tipoId,
                'competencia' => $competencia,
            ]
        ) !== null;
    }

    /**
     * @return list<array{id: int, nome: string}>
     */
    public function opcoesEmpresas(int $escritorioId): array
    {
        return $this->opcoes(
            'SELECT id, razao_social AS nome FROM empresas
             WHERE escritorio_id = :escritorio_id ORDER BY razao_social ASC, id ASC',
            $escritorioId
        );
    }

    /**
     * @return list<array{id: int, nome: string}>
     */
    public function opcoesTipos(int $escritorioId, bool $somenteAtivos = false): array
    {
        return $this->opcoes(
            'SELECT id, nome FROM tipos_obrigacao WHERE escritorio_id = :escritorio_id'
            . ($somenteAtivos ? ' AND ativo = 1' : '')
            . ' ORDER BY nome ASC, id ASC',
            $escritorioId
        );
    }

    /**
     * @param array<string, mixed> $filtros
     * @return array{list<string>, array<string, mixed>}
     */
    private function condicoes(int $escritorioId, array $filtros): array
    {
        $condicoes = ['o.escritorio_id = :escritorio_id'];
        $parametros = ['hoje' => $this->hoje(), 'escritorio_id' => $escritorioId];

        $busca = $this->texto($filtros, 'q');
        if ($busca !== '') {
            $termo = '%' . QueryFiltros::like($busca) . '%';
            $condicoes[] = "(e.razao_social LIKE :termo_empresa ESCAPE '\\\\'
                OR e.nome_fantasia LIKE :termo_fantasia ESCAPE '\\\\'
                OR t.nome LIKE :termo_tipo ESCAPE '\\\\')";
            $parametros += ['termo_empresa' => $termo, 'termo_fantasia' => $termo, 'termo_tipo' => $termo];
        }

        foreach (['empresa_id' => 'o.empresa_id', 'tipo_id' => 'o.tipo_obrigacao_id', 'responsavel' => 'o.responsavel_id'] as $filtro => $coluna) {
            $id = $this->texto($filtros, $filtro);
            if (preg_match('/\A[1-9][0-9]{0,9}\z/', $id) === 1) {
                $condicoes[] = $coluna . ' = :' . $filtro;
                $parametros[$filtro] = (int) $id;
            }
        }

        $status = $this->texto($filtros, 'status');
        if (in_array($status, self::STATUS, true)) {
            $condicoes[] = 'o.status = :status';
            $parametros['status'] = $status;
        }

        $situacao = $this->condicaoDaSituacao($this->texto($filtros, 'situacao'));
        if ($situacao !== null) {
            $condicoes[] = '(' . $situacao . ')';
        }

        return $this->condicoesDeData($filtros, $condicoes, $parametros);
    }

    /**
     * Fórmulas de ARCHITECTURE §10.1; vence_hoje e vence_7d contam só obrigações em aberto,
     * e vence_7d vai de hoje até hoje + 7 dias (decisão registrada na FI03).
     */
    private function condicaoDaSituacao(string $situacao): ?string
    {
        return match ($situacao) {
            'atrasada' => self::ATRASADA,
            'vence_hoje' => self::ABERTA . ' AND o.vencimento = ref.hoje',
            'vence_7d' => self::ABERTA . ' AND o.vencimento BETWEEN ref.hoje AND DATE_ADD(ref.hoje, INTERVAL 7 DAY)',
            'entregue_atraso' => self::ENTREGUE_COM_ATRASO,
            default => null,
        };
    }

    /**
     * @param array<string, mixed> $filtros
     * @param list<string> $condicoes
     * @param array<string, mixed> $parametros
     * @return array{list<string>, array<string, mixed>}
     */
    private function condicoesDeData(array $filtros, array $condicoes, array $parametros): array
    {
        try {
            $competencia = Competencia::deTexto($this->texto($filtros, 'competencia'));
            $condicoes[] = 'o.competencia = :competencia';
            $parametros['competencia'] = $competencia->primeiroDia()->format('Y-m-d');
        } catch (InvalidArgumentException) {
            // Competência ausente ou inválida: o filtro é ignorado.
        }

        foreach (['de' => 'o.vencimento >= :de', 'ate' => 'o.vencimento <= :ate'] as $filtro => $condicao) {
            $data = $this->texto($filtros, $filtro);
            $lida = DateTimeImmutable::createFromFormat('!Y-m-d', $data);
            if ($lida !== false && $lida->format('Y-m-d') === $data) {
                $condicoes[] = $condicao;
                $parametros[$filtro] = $data;
            }
        }

        return [$condicoes, $parametros];
    }

    /**
     * `?ordem=campo` ou `?ordem=-campo` (decrescente), só entre os campos da API.
     */
    private function ordenacao(mixed $ordem): string
    {
        $ordem = is_string($ordem) ? $ordem : '';
        $direcao = str_starts_with($ordem, '-') ? 'DESC' : 'ASC';
        $coluna = match (ltrim($ordem, '-')) {
            'competencia' => 'o.competencia',
            'empresa' => 'e.razao_social',
            default => 'o.vencimento',
        };

        return QueryFiltros::ordenacao(
            $coluna . ' ' . $direcao,
            ['o.vencimento', 'o.competencia', 'e.razao_social'],
            'o.vencimento ASC'
        );
    }

    /**
     * @param array<string, mixed> $filtros
     */
    private function texto(array $filtros, string $campo): string
    {
        $valor = $filtros[$campo] ?? '';

        return is_string($valor) || is_int($valor) ? trim((string) $valor) : '';
    }

    private function hoje(): string
    {
        return $this->relogio->hoje()->format('Y-m-d');
    }

    /**
     * @return list<array{id: int, nome: string}>
     */
    private function opcoes(string $sql, int $escritorioId): array
    {
        return array_map(
            static fn (array $linha): array => ['id' => (int) $linha['id'], 'nome' => (string) $linha['nome']],
            $this->buscarTodos($sql, ['escritorio_id' => $escritorioId])
        );
    }

    private static function textoOuNulo(mixed $valor): ?string
    {
        return is_string($valor) ? $valor : null;
    }
}
