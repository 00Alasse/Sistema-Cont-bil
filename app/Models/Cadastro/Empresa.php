<?php

declare(strict_types=1);

namespace App\Models\Cadastro;

use App\Core\Model;
use App\Core\Pagina;
use App\Core\Paginacao;
use App\Core\QueryFiltros;
use App\Exceptions\ConflitoException;
use App\Support\Cnpj;

final class Empresa extends Model
{
    /**
     * @param array<string, mixed> $filtros
     */
    public function listar(int $escritorioId, array $filtros, Paginacao $paginacao): Pagina
    {
        $condicoes = ['e.escritorio_id = :escritorio_id'];
        $parametros = ['escritorio_id' => $escritorioId];

        $busca = $filtros['q'] ?? null;
        if (is_string($busca) && trim($busca) !== '') {
            $busca = trim($busca);
            $condicoes[] = "(e.razao_social LIKE :termo_nome ESCAPE '\\\\'
                OR e.nome_fantasia LIKE :termo_fantasia ESCAPE '\\\\'
                OR e.cnpj LIKE :termo_cnpj ESCAPE '\\\\')";
            $parametros['termo_nome'] = '%' . QueryFiltros::like($busca) . '%';
            $parametros['termo_fantasia'] = '%' . QueryFiltros::like($busca) . '%';
            $parametros['termo_cnpj'] = '%' . QueryFiltros::like(Cnpj::normalizar($busca)) . '%';
        }

        foreach (['status' => 'e.status', 'regime' => 'e.regime_tributario'] as $filtro => $coluna) {
            $valor = $filtros[$filtro] ?? null;
            if (is_string($valor) && $valor !== '') {
                $condicoes[] = $coluna . ' = :' . $filtro;
                $parametros[$filtro] = $valor;
            }
        }

        $responsavel = $filtros['responsavel_id'] ?? null;
        if ((is_string($responsavel) || is_int($responsavel)) && (string) $responsavel !== '') {
            $condicoes[] = 'e.responsavel_id = :responsavel_id';
            $parametros['responsavel_id'] = (string) $responsavel;
        }

        $servico = $filtros['servico'] ?? null;
        if ($servico === 'bpo') {
            $condicoes[] = 'e.bpo_ativo = 1';
        } elseif ($servico === 'consultivo') {
            $condicoes[] = 'e.consultivo_ativo = 1';
        }

        $ordemSolicitada = match ($filtros['ordem'] ?? null) {
            'criado_em' => 'e.criado_em DESC',
            default => 'e.razao_social ASC',
        };
        $ordenacao = QueryFiltros::ordenacao(
            $ordemSolicitada,
            ['e.razao_social', 'e.criado_em'],
            'e.razao_social ASC'
        );
        $where = implode(' AND ', $condicoes);

        return $this->paginar(
            "SELECT e.*, u.nome AS responsavel_nome
             FROM empresas e
             LEFT JOIN usuarios u
                ON u.id = e.responsavel_id AND u.escritorio_id = e.escritorio_id
             WHERE {$where}
             ORDER BY {$ordenacao}, e.id DESC",
            "SELECT COUNT(*) FROM empresas e WHERE {$where}",
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
            'SELECT e.*, u.nome AS responsavel_nome
             FROM empresas e
             LEFT JOIN usuarios u
                ON u.id = e.responsavel_id AND u.escritorio_id = e.escritorio_id
             WHERE e.escritorio_id = :escritorio_id AND e.id = :id',
            ['escritorio_id' => $escritorioId, 'id' => $id]
        );
    }

    /**
     * @param array<string, mixed> $dados
     */
    public function criar(int $escritorioId, array $dados): int
    {
        return $this->inserir(
            'INSERT INTO empresas (
                escritorio_id, razao_social, nome_fantasia, cnpj, regime_tributario,
                segmento, contato_nome, email, telefone, responsavel_id, bpo_ativo,
                consultivo_ativo, observacoes
             ) VALUES (
                :escritorio_id, :razao_social, :nome_fantasia, :cnpj, :regime_tributario,
                :segmento, :contato_nome, :email, :telefone, :responsavel_id, :bpo_ativo,
                :consultivo_ativo, :observacoes
             )',
            [
                'escritorio_id' => $escritorioId,
                'razao_social' => $dados['razao_social'],
                'nome_fantasia' => $dados['nome_fantasia'],
                'cnpj' => $dados['cnpj'],
                'regime_tributario' => $dados['regime_tributario'],
                'segmento' => $dados['segmento'],
                'contato_nome' => $dados['contato_nome'],
                'email' => $dados['email'],
                'telefone' => $dados['telefone'],
                'responsavel_id' => $dados['responsavel_id'],
                'bpo_ativo' => $dados['bpo_ativo'],
                'consultivo_ativo' => $dados['consultivo_ativo'],
                'observacoes' => $dados['observacoes'],
            ]
        );
    }

    /**
     * @param array<string, mixed> $dados
     */
    public function atualizar(int $escritorioId, int $id, array $dados, int $versao): void
    {
        $afetadas = $this->executar(
            'UPDATE empresas
             SET razao_social = :razao_social,
                 nome_fantasia = :nome_fantasia,
                 cnpj = :cnpj,
                 regime_tributario = :regime_tributario,
                 segmento = :segmento,
                 contato_nome = :contato_nome,
                 email = :email,
                 telefone = :telefone,
                 responsavel_id = :responsavel_id,
                 bpo_ativo = :bpo_ativo,
                 consultivo_ativo = :consultivo_ativo,
                 observacoes = :observacoes,
                 versao = versao + 1
             WHERE escritorio_id = :escritorio_id AND id = :id AND versao = :versao',
            [
                'razao_social' => $dados['razao_social'],
                'nome_fantasia' => $dados['nome_fantasia'],
                'cnpj' => $dados['cnpj'],
                'regime_tributario' => $dados['regime_tributario'],
                'segmento' => $dados['segmento'],
                'contato_nome' => $dados['contato_nome'],
                'email' => $dados['email'],
                'telefone' => $dados['telefone'],
                'responsavel_id' => $dados['responsavel_id'],
                'bpo_ativo' => $dados['bpo_ativo'],
                'consultivo_ativo' => $dados['consultivo_ativo'],
                'observacoes' => $dados['observacoes'],
                'escritorio_id' => $escritorioId,
                'id' => $id,
                'versao' => $versao,
            ]
        );
        if ($afetadas !== 1) {
            throw new ConflitoException();
        }
    }

    public function excluir(int $escritorioId, int $id): int
    {
        return $this->executar(
            'DELETE FROM empresas WHERE escritorio_id = :escritorio_id AND id = :id',
            ['escritorio_id' => $escritorioId, 'id' => $id]
        );
    }

    public function cnpjJaCadastrado(int $escritorioId, string $cnpj, ?int $ignorarId = null): bool
    {
        $sql = 'SELECT 1 FROM empresas WHERE escritorio_id = :escritorio_id AND cnpj = :cnpj';
        $parametros = ['escritorio_id' => $escritorioId, 'cnpj' => $cnpj];
        if ($ignorarId !== null) {
            $sql .= ' AND id <> :ignorar_id';
            $parametros['ignorar_id'] = $ignorarId;
        }

        return $this->buscarUm($sql . ' LIMIT 1', $parametros) !== null;
    }

    public function responsavelValido(int $escritorioId, int $responsavelId): bool
    {
        return $this->buscarUm(
            "SELECT id FROM usuarios
             WHERE escritorio_id = :escritorio_id
               AND id = :responsavel_id
               AND ativo = 1
               AND perfil IN ('ADMIN', 'COLABORADOR')",
            ['escritorio_id' => $escritorioId, 'responsavel_id' => $responsavelId]
        ) !== null;
    }

    /**
     * @return list<array{id: int, nome: string, perfil: string}>
     */
    public function listarResponsaveis(int $escritorioId): array
    {
        $registros = $this->buscarTodos(
            "SELECT id, nome, perfil FROM usuarios
             WHERE escritorio_id = :escritorio_id
               AND ativo = 1
               AND perfil IN ('ADMIN', 'COLABORADOR')
             ORDER BY nome ASC, id ASC",
            ['escritorio_id' => $escritorioId]
        );

        return array_map(
            static fn (array $registro): array => [
                'id' => (int) $registro['id'],
                'nome' => (string) $registro['nome'],
                'perfil' => (string) $registro['perfil'],
            ],
            $registros
        );
    }
}
