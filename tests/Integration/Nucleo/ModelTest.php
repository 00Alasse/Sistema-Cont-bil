<?php

declare(strict_types=1);

namespace Tests\Integration\Nucleo;

use App\Core\Paginacao;
use App\Core\Request;
use PDO;
use Tests\Integration\IntegrationTestCase;

final class ModelTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->conexao()->exec('DROP TEMPORARY TABLE IF EXISTS f06_model_teste');
        $this->conexao()->exec(
            'CREATE TEMPORARY TABLE f06_model_teste (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                escritorio_id INT UNSIGNED NOT NULL,
                nome VARCHAR(80) NOT NULL
            ) ENGINE=InnoDB'
        );
    }

    protected function tearDown(): void
    {
        $this->conexao()->exec('DROP TEMPORARY TABLE IF EXISTS f06_model_teste');
        parent::tearDown();
    }

    public function testConsultasComParametrosNomeadosIsolamPorEscritorio(): void
    {
        $this->inserirLinha(11, 'Empresa onze');
        $this->inserirLinha(22, 'Empresa vinte e dois');
        $model = new ModelF06Teste($this->conexao());

        $registro = $model->consultarUm(
            'SELECT id, escritorio_id, nome FROM f06_model_teste
             WHERE escritorio_id = :escritorio_id AND id = :id',
            ['escritorio_id' => 11, 'id' => 1]
        );
        $naoAutorizado = $model->consultarUm(
            'SELECT id, escritorio_id, nome FROM f06_model_teste
             WHERE escritorio_id = :escritorio_id AND id = :id',
            ['escritorio_id' => 22, 'id' => 1]
        );
        $registros = $model->consultarTodos(
            'SELECT id, escritorio_id, nome FROM f06_model_teste
             WHERE escritorio_id = :escritorio_id',
            ['escritorio_id' => 22]
        );

        self::assertSame('Empresa onze', $registro['nome'] ?? null);
        self::assertNull($naoAutorizado);
        self::assertCount(1, $registros);
        self::assertSame(22, (int) $registros[0]['escritorio_id']);
    }

    public function testInserirEExecutarUsamParametrosNomeados(): void
    {
        $model = new ModelF06Teste($this->conexao());
        $id = $model->criarRegistro(
            'INSERT INTO f06_model_teste (escritorio_id, nome)
             VALUES (:escritorio_id, :nome)',
            ['escritorio_id' => 11, 'nome' => 'Original']
        );

        $alteradas = $model->executarComParametros(
            'UPDATE f06_model_teste SET nome = :nome
             WHERE escritorio_id = :escritorio_id AND id = :id',
            ['nome' => 'Atualizada', 'escritorio_id' => 11, 'id' => $id]
        );
        $naoAlteradas = $model->executarComParametros(
            'UPDATE f06_model_teste SET nome = :nome
             WHERE escritorio_id = :escritorio_id AND id = :id',
            ['nome' => 'Invasao', 'escritorio_id' => 22, 'id' => $id]
        );

        self::assertGreaterThan(0, $id);
        self::assertSame(1, $alteradas);
        self::assertSame(0, $naoAlteradas);
        self::assertSame(
            'Atualizada',
            $model->consultarUm(
                'SELECT nome FROM f06_model_teste
                 WHERE escritorio_id = :escritorio_id AND id = :id',
                ['escritorio_id' => 11, 'id' => $id]
            )['nome'] ?? null
        );
    }

    public function testPaginarUsaPaginaFinalETrataTotalZero(): void
    {
        foreach (['Um', 'Dois', 'Tres', 'Quatro', 'Cinco'] as $nome) {
            $this->inserirLinha(11, $nome);
        }

        $model = new ModelF06Teste($this->conexao());
        $paginaSolicitada = Paginacao::deRequest(
            new Request('GET', '/', ['pagina' => 99]),
            2
        );
        $paginaFinal = $model->paginarConsulta(
            'SELECT id, escritorio_id, nome FROM f06_model_teste
             WHERE escritorio_id = :escritorio_id ORDER BY id',
            'SELECT COUNT(*) FROM f06_model_teste WHERE escritorio_id = :escritorio_id',
            ['escritorio_id' => 11],
            $paginaSolicitada
        );
        $paginaVazia = $model->paginarConsulta(
            'SELECT id, escritorio_id, nome FROM f06_model_teste
             WHERE escritorio_id = :escritorio_id ORDER BY id',
            'SELECT COUNT(*) FROM f06_model_teste WHERE escritorio_id = :escritorio_id',
            ['escritorio_id' => 99],
            $paginaSolicitada
        );

        self::assertSame(5, $paginaFinal->total);
        self::assertSame(3, $paginaFinal->pagina);
        self::assertSame(3, $paginaFinal->totalPaginas);
        self::assertCount(1, $paginaFinal->itens);
        self::assertSame(0, $paginaVazia->total);
        self::assertSame(1, $paginaVazia->pagina);
        self::assertSame(0, $paginaVazia->totalPaginas);
        self::assertSame([], $paginaVazia->itens);
    }

    private function inserirLinha(int $escritorioId, string $nome): void
    {
        $stmt = $this->conexao()->prepare(
            'INSERT INTO f06_model_teste (escritorio_id, nome)
             VALUES (:escritorio_id, :nome)'
        );
        $stmt->execute(['escritorio_id' => $escritorioId, 'nome' => $nome]);
    }
}

final class ModelF06Teste extends \App\Core\Model
{
    /**
     * @param array<string, mixed> $parametros
     * @return array<string, mixed>|null
     */
    public function consultarUm(string $sql, array $parametros): ?array
    {
        return $this->buscarUm($sql, $parametros);
    }

    /**
     * @param array<string, mixed> $parametros
     * @return list<array<string, mixed>>
     */
    public function consultarTodos(string $sql, array $parametros): array
    {
        return $this->buscarTodos($sql, $parametros);
    }

    /**
     * @param array<string, mixed> $parametros
     */
    public function criarRegistro(string $sql, array $parametros): int
    {
        return $this->inserir($sql, $parametros);
    }

    /**
     * @param array<string, mixed> $parametros
     */
    public function executarComParametros(string $sql, array $parametros): int
    {
        return $this->executar($sql, $parametros);
    }

    /**
     * @param array<string, mixed> $parametros
     */
    public function paginarConsulta(
        string $sqlSelect,
        string $sqlCount,
        array $parametros,
        Paginacao $paginacao
    ): \App\Core\Pagina {
        return $this->paginar($sqlSelect, $sqlCount, $parametros, $paginacao);
    }
}
