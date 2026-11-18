<?php

declare(strict_types=1);

namespace App\Models\Documento;

use App\Core\Model;
use App\Core\Pagina;
use App\Core\Paginacao;
use App\Core\QueryFiltros;
use App\Support\Relogio;
use App\Support\RelogioSistema;
use PDO;

final class SolicitacaoDocumento extends Model
{
    public const STATUS = ['aberta', 'enviada', 'aceita', 'recusada'];

    private readonly Relogio $relogio;

    public function __construct(?RelogioSistema $relogio = null, ?PDO $db = null)
    {
        parent::__construct($db);
        $this->relogio = $relogio ?? new RelogioSistema();
    }

    /**
     * @param array<string, mixed> $filtros
     */
    public function listar(int $escritorioId, array $filtros, Paginacao $paginacao): Pagina
    {
        $hoje = $this->relogio->hoje()->format('Y-m-d');
        $condicoes = ['s.escritorio_id = :escritorio_id'];
        $params = [
            'escritorio_id' => $escritorioId,
            'hoje' => $hoje,
        ];

        if (!empty($filtros['empresa_id'])) {
            $condicoes[] = 's.empresa_id = :empresa_id';
            $params['empresa_id'] = (int) $filtros['empresa_id'];
        }

        if (!empty($filtros['status']) && in_array($filtros['status'], self::STATUS, true)) {
            $condicoes[] = 's.status = :status';
            $params['status'] = $filtros['status'];
        }

        // "vencida" é o mesmo critério do indicador "Prazo de envio vencido" (contarAtrasadas):
        // aberta ou recusada com prazo anterior a hoje. Parâmetro próprio porque a contagem não usa :hoje.
        if (($filtros['situacao'] ?? null) === 'vencida') {
            $condicoes[] = "s.status IN ('aberta', 'recusada') AND s.prazo < :hoje_vencida";
            $params['hoje_vencida'] = $hoje;
        }

        if (!empty($filtros['busca'])) {
            // Um parâmetro por coluna: com prepares nativos, repetir :busca gera HY093.
            $condicoes[] = '(s.titulo LIKE :busca1 OR e.razao_social LIKE :busca2 OR e.nome_fantasia LIKE :busca3)';
            $termo = '%' . QueryFiltros::like((string) $filtros['busca']) . '%';
            $params['busca1'] = $termo;
            $params['busca2'] = $termo;
            $params['busca3'] = $termo;
        }

        $where = implode(' AND ', $condicoes);

        $sqlConta = "SELECT COUNT(*) FROM solicitacoes_documentos s
                     JOIN empresas e ON e.id = s.empresa_id AND e.escritorio_id = s.escritorio_id
                     WHERE {$where}";
        $stmtConta = $this->db->prepare($sqlConta);
        $paramsConta = $params;
        unset($paramsConta['hoje']);
        $this->vincularParametrosConta($stmtConta, $paramsConta);
        $stmtConta->execute();
        $total = (int) $stmtConta->fetchColumn();

        $offset = ($paginacao->pagina - 1) * $paginacao->porPagina;

        $sql = "SELECT s.*, e.razao_social AS empresa_nome, e.nome_fantasia, u.nome AS criador_nome,
                       (s.status IN ('aberta', 'recusada') AND s.prazo < :hoje) AS atrasada
                FROM solicitacoes_documentos s
                JOIN empresas e ON e.id = s.empresa_id AND e.escritorio_id = s.escritorio_id
                JOIN usuarios u ON u.id = s.criado_por AND u.escritorio_id = s.escritorio_id
                WHERE {$where}
                ORDER BY s.prazo ASC, s.id DESC
                LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v);
        }
        $stmt->bindValue(':limit', $paginacao->porPagina, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        /** @var list<array<string, mixed>> $itens */
        $itens = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return new Pagina($itens, $total, $paginacao->pagina, $paginacao->porPagina);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listarPorEmpresa(int $escritorioId, int $empresaId): array
    {
        $hoje = $this->relogio->hoje()->format('Y-m-d');
        $sql = "SELECT s.*, (s.status IN ('aberta', 'recusada') AND s.prazo < :hoje) AS atrasada
                FROM solicitacoes_documentos s
                WHERE s.escritorio_id = :escritorio_id AND s.empresa_id = :empresa_id
                ORDER BY CASE WHEN s.status IN ('aberta', 'recusada') THEN 1 WHEN s.status = 'enviada' THEN 2 ELSE 3 END,
                         s.prazo ASC, s.id DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':escritorio_id', $escritorioId, PDO::PARAM_INT);
        $stmt->bindValue(':empresa_id', $empresaId, PDO::PARAM_INT);
        $stmt->bindValue(':hoje', $hoje);
        $stmt->execute();

        /** @var list<array<string, mixed>> $registros */
        $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $registros;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function buscar(int $escritorioId, int $id): ?array
    {
        $hoje = $this->relogio->hoje()->format('Y-m-d');
        $sql = "SELECT s.*, e.razao_social AS empresa_nome, e.nome_fantasia,
                       (s.status IN ('aberta', 'recusada') AND s.prazo < :hoje) AS atrasada
                FROM solicitacoes_documentos s
                JOIN empresas e ON e.id = s.empresa_id AND e.escritorio_id = s.escritorio_id
                WHERE s.id = :id AND s.escritorio_id = :escritorio_id
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':escritorio_id', $escritorioId, PDO::PARAM_INT);
        $stmt->bindValue(':hoje', $hoje);
        $stmt->execute();

        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($item) ? $item : null;
    }

    /**
     * @param array{
     *   empresa_id: int,
     *   titulo: string,
     *   descricao?: ?string,
     *   prazo: string,
     *   criado_por: int
     * } $dados
     */
    public function criar(int $escritorioId, array $dados): int
    {
        $sql = "INSERT INTO solicitacoes_documentos (
                    escritorio_id, empresa_id, titulo, descricao, prazo, status, criado_por
                ) VALUES (
                    :escritorio_id, :empresa_id, :titulo, :descricao, :prazo, 'aberta', :criado_por
                )";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':escritorio_id', $escritorioId, PDO::PARAM_INT);
        $stmt->bindValue(':empresa_id', $dados['empresa_id'], PDO::PARAM_INT);
        $stmt->bindValue(':titulo', $dados['titulo']);
        $stmt->bindValue(':descricao', $dados['descricao'] ?? null);
        $stmt->bindValue(':prazo', $dados['prazo']);
        $stmt->bindValue(':criado_por', $dados['criado_por'], PDO::PARAM_INT);
        $stmt->execute();

        return (int) $this->db->lastInsertId();
    }

    public function anexarArquivo(
        int $escritorioId,
        int $id,
        string $arquivoPath,
        string $arquivoNomeOriginal,
        string $enviadoEm
    ): bool {
        $sql = "UPDATE solicitacoes_documentos
                SET arquivo_path = :path,
                    arquivo_nome_original = :nome_original,
                    enviado_em = :enviado_em,
                    status = 'enviada',
                    motivo_recusa = NULL
                WHERE id = :id AND escritorio_id = :escritorio_id";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':path', $arquivoPath);
        $stmt->bindValue(':nome_original', $arquivoNomeOriginal);
        $stmt->bindValue(':enviado_em', $enviadoEm);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':escritorio_id', $escritorioId, PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function aceitar(int $escritorioId, int $id): bool
    {
        $sql = "UPDATE solicitacoes_documentos
                SET status = 'aceita',
                    motivo_recusa = NULL
                WHERE id = :id AND escritorio_id = :escritorio_id";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':escritorio_id', $escritorioId, PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function recusar(int $escritorioId, int $id, string $motivo): bool
    {
        $sql = "UPDATE solicitacoes_documentos
                SET status = 'recusada',
                    motivo_recusa = :motivo
                WHERE id = :id AND escritorio_id = :escritorio_id";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':motivo', $motivo);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':escritorio_id', $escritorioId, PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * Histórico das solicitações (Melhoria 2), em ordem cronológica, agrupado por solicitação.
     * Só lê eventos do próprio escritório e das solicitações pedidas.
     *
     * @param list<int> $ids
     * @return array<int, list<array{acao: string, criado_em: string, usuario_nome: ?string, arquivo: ?string, motivo: ?string}>>
     */
    public function historico(int $escritorioId, array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids, static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return [];
        }

        // Um placeholder por ID: nada vindo de fora é concatenado ao SQL.
        $marcadores = [];
        $params = ['escritorio_id' => $escritorioId];
        foreach ($ids as $posicao => $id) {
            $marcadores[] = ':id' . $posicao;
            $params['id' . $posicao] = $id;
        }

        $linhas = $this->buscarTodos(
            "SELECT l.entidade_id, l.acao, l.criado_em, l.detalhes, u.nome AS usuario_nome
             FROM logs_atividade l
             LEFT JOIN usuarios u ON u.id = l.usuario_id AND u.escritorio_id = l.escritorio_id
             WHERE l.escritorio_id = :escritorio_id
               AND l.entidade = 'solicitacao'
               AND l.entidade_id IN (" . implode(', ', $marcadores) . ')
             ORDER BY l.criado_em ASC, l.id ASC',
            $params
        );

        $historico = [];
        foreach ($linhas as $linha) {
            $detalhes = json_decode((string) ($linha['detalhes'] ?? ''), true);
            $detalhes = is_array($detalhes) ? $detalhes : [];
            $historico[(int) $linha['entidade_id']][] = [
                'acao' => (string) $linha['acao'],
                'criado_em' => (string) $linha['criado_em'],
                'usuario_nome' => is_string($linha['usuario_nome'] ?? null) ? $linha['usuario_nome'] : null,
                'arquivo' => is_string($detalhes['arquivo'] ?? null) ? $detalhes['arquivo'] : null,
                'motivo' => is_string($detalhes['motivo'] ?? null) ? $detalhes['motivo'] : null,
            ];
        }

        return $historico;
    }

    public function contarPendenciasEmpresa(int $escritorioId, int $empresaId): int
    {
        $sql = "SELECT COUNT(*) FROM solicitacoes_documentos
                WHERE escritorio_id = :escritorio_id
                  AND empresa_id = :empresa_id
                  AND status IN ('aberta', 'recusada')";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':escritorio_id', $escritorioId, PDO::PARAM_INT);
        $stmt->bindValue(':empresa_id', $empresaId, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public function contarAguardandoConferencia(int $escritorioId): int
    {
        $sql = "SELECT COUNT(*) FROM solicitacoes_documentos
                WHERE escritorio_id = :escritorio_id AND status = 'enviada'";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':escritorio_id', $escritorioId, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public function contarAtrasadas(int $escritorioId): int
    {
        $hoje = $this->relogio->hoje()->format('Y-m-d');
        $sql = "SELECT COUNT(*) FROM solicitacoes_documentos
                WHERE escritorio_id = :escritorio_id
                  AND status IN ('aberta', 'recusada')
                  AND prazo < :hoje";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':escritorio_id', $escritorioId, PDO::PARAM_INT);
        $stmt->bindValue(':hoje', $hoje);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    /**
     * @param array<string, mixed> $params
     */
    private function vincularParametrosConta(\PDOStatement $stmt, array $params): void
    {
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v);
        }
    }
}
