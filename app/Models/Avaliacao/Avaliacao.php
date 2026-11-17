<?php

declare(strict_types=1);

namespace App\Models\Avaliacao;

use App\Core\Model;
use App\Core\Pagina;
use App\Core\Paginacao;
use PDO;

final class Avaliacao extends Model
{
    /**
     * @return Pagina
     */
    public function listar(int $escritorioId, Paginacao $paginacao): Pagina
    {
        $sqlConta = "SELECT COUNT(*) FROM avaliacoes WHERE escritorio_id = :escritorio_id";
        $stmtConta = $this->db->prepare($sqlConta);
        $stmtConta->bindValue(':escritorio_id', $escritorioId, PDO::PARAM_INT);
        $stmtConta->execute();
        $total = (int) $stmtConta->fetchColumn();

        $offset = ($paginacao->pagina - 1) * $paginacao->porPagina;

        $sql = "SELECT a.*, u.nome AS usuario_nome, u.perfil AS usuario_perfil, e.razao_social AS empresa_nome
                FROM avaliacoes a
                JOIN usuarios u ON u.id = a.usuario_id AND u.escritorio_id = a.escritorio_id
                LEFT JOIN empresas e ON e.id = u.empresa_id AND e.escritorio_id = a.escritorio_id
                WHERE a.escritorio_id = :escritorio_id
                ORDER BY a.criado_em DESC, a.id DESC
                LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':escritorio_id', $escritorioId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $paginacao->porPagina, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        /** @var list<array<string, mixed>> $itens */
        $itens = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return new Pagina($itens, $total, $paginacao->pagina, $paginacao->porPagina);
    }

    public function criar(
        int $escritorioId,
        int $usuarioId,
        int $nota,
        ?int $facilidade,
        ?string $comentario
    ): int {
        $sql = "INSERT INTO avaliacoes (escritorio_id, usuario_id, nota, facilidade, comentario)
                VALUES (:escritorio_id, :usuario_id, :nota, :facilidade, :comentario)";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':escritorio_id', $escritorioId, PDO::PARAM_INT);
        $stmt->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);
        $stmt->bindValue(':nota', $nota, PDO::PARAM_INT);
        $stmt->bindValue(':facilidade', $facilidade, $facilidade !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':comentario', $comentario);
        $stmt->execute();

        return (int) $this->db->lastInsertId();
    }

    /**
     * @return array{total: int, media_nota: float, media_facilidade: float}
     */
    public function estatisticas(int $escritorioId): array
    {
        $sql = "SELECT COUNT(*) AS total,
                       AVG(nota) AS media_nota,
                       AVG(facilidade) AS media_facilidade
                FROM avaliacoes
                WHERE escritorio_id = :escritorio_id";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':escritorio_id', $escritorioId, PDO::PARAM_INT);
        $stmt->execute();

        $dados = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($dados)) {
            return ['total' => 0, 'media_nota' => 0.0, 'media_facilidade' => 0.0];
        }

        return [
            'total' => (int) ($dados['total'] ?? 0),
            'media_nota' => round((float) ($dados['media_nota'] ?? 0.0), 1),
            'media_facilidade' => round((float) ($dados['media_facilidade'] ?? 0.0), 1),
        ];
    }
}
