<?php

declare(strict_types=1);

namespace App\Core;

use InvalidArgumentException;
use PDO;
use PDOStatement;
use RuntimeException;

abstract class Model
{
    protected PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::conexao();
    }

    /**
     * @param array<string, mixed> $parametros
     * @return array<string, mixed>|null
     */
    protected function buscarUm(string $sql, array $parametros): ?array
    {
        $registro = $this->prepararEExecutar($sql, $parametros)->fetch(PDO::FETCH_ASSOC);

        return is_array($registro) ? $registro : null;
    }

    /**
     * @param array<string, mixed> $parametros
     * @return list<array<string, mixed>>
     */
    protected function buscarTodos(string $sql, array $parametros): array
    {
        $registros = $this->prepararEExecutar($sql, $parametros)->fetchAll(PDO::FETCH_ASSOC);

        return array_values($registros);
    }

    /**
     * @param array<string, mixed> $parametros
     */
    protected function executar(string $sql, array $parametros): int
    {
        return $this->prepararEExecutar($sql, $parametros)->rowCount();
    }

    /**
     * @param array<string, mixed> $parametros
     */
    protected function inserir(string $sql, array $parametros): int
    {
        $this->prepararEExecutar($sql, $parametros);
        $id = $this->db->lastInsertId();

        return $id === false ? 0 : (int) $id;
    }

    /**
     * @param array<string, mixed> $parametros
     */
    protected function paginar(
        string $sqlSelect,
        string $sqlCount,
        array $parametros,
        Paginacao $paginacao
    ): Pagina {
        $totalBruto = $this->prepararEExecutar($sqlCount, $parametros)->fetchColumn();
        if (!is_numeric($totalBruto)) {
            throw new RuntimeException('A consulta de contagem não retornou um total numérico.');
        }

        $total = max(0, (int) $totalBruto);
        $totalPaginas = $total === 0
            ? 0
            : intdiv($total - 1, $paginacao->porPagina) + 1;
        $pagina = $totalPaginas === 0 ? 1 : min($paginacao->pagina, $totalPaginas);
        $offset = ($pagina - 1) * $paginacao->porPagina;
        [$consulta, $parametrosConsulta] = $this->adicionarLimite(
            $sqlSelect,
            $parametros,
            $paginacao->porPagina,
            $offset
        );
        $itens = $this->buscarTodos($consulta, $parametrosConsulta);

        return new Pagina($itens, $total, $pagina, $paginacao->porPagina);
    }

    /**
     * @param array<string, mixed> $parametros
     * @return array{string, array<string, mixed>}
     */
    private function adicionarLimite(
        string $sql,
        array $parametros,
        int $limite,
        int $offset
    ): array {
        $nomeLimite = $this->nomeParametroDisponivel($parametros, '__tributo_limite');
        $nomeOffset = $this->nomeParametroDisponivel($parametros, '__tributo_offset');
        $parametros[$nomeLimite] = $limite;
        $parametros[$nomeOffset] = $offset;
        $sql = rtrim($sql, " \t\n\r\0\x0B;");

        return [
            $sql . ' LIMIT :' . $nomeLimite . ' OFFSET :' . $nomeOffset,
            $parametros,
        ];
    }

    /**
     * @param array<string, mixed> $parametros
     */
    private function nomeParametroDisponivel(array $parametros, string $base): string
    {
        $nome = $base;
        $sufixo = 1;
        while (array_key_exists($nome, $parametros) || array_key_exists(':' . $nome, $parametros)) {
            $nome = $base . '_' . $sufixo;
            $sufixo++;
        }

        return $nome;
    }

    /**
     * @param array<array-key, mixed> $parametros
     */
    private function prepararEExecutar(string $sql, array $parametros): PDOStatement
    {
        $stmt = $this->db->prepare($sql);
        $nomesVinculados = [];

        foreach ($parametros as $nome => $valor) {
            if (!is_string($nome) || preg_match('/\A:?[A-Za-z_][A-Za-z0-9_]*\z/', $nome) !== 1) {
                throw new InvalidArgumentException('Os parâmetros SQL devem ter nomes válidos.');
            }

            $nomeNormalizado = ltrim($nome, ':');
            if (isset($nomesVinculados[$nomeNormalizado])) {
                throw new InvalidArgumentException('Um parâmetro SQL foi informado mais de uma vez.');
            }
            $nomesVinculados[$nomeNormalizado] = true;

            $stmt->bindValue(
                ':' . $nomeNormalizado,
                $valor,
                $this->tipoParametro($valor)
            );
        }

        $stmt->execute();

        return $stmt;
    }

    private function tipoParametro(mixed $valor): int
    {
        return match (true) {
            is_int($valor) => PDO::PARAM_INT,
            is_bool($valor) => PDO::PARAM_BOOL,
            $valor === null => PDO::PARAM_NULL,
            is_string($valor) => PDO::PARAM_STR,
            default => throw new InvalidArgumentException('O valor do parâmetro SQL não é compatível.'),
        };
    }
}
