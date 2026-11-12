<?php

declare(strict_types=1);

namespace Tests\Integration\Fiscal;

use App\Support\Cnpj;
use PDOException;
use Tests\Integration\IntegrationTestCase;
use Tests\Support\FabricaCadastro;
use Tests\Support\FabricaFiscal;

/**
 * Automatiza as verificações manuais do fiscal (DATABASE §8.2) e valida os seeds.
 */
final class SchemaFiscalTest extends IntegrationTestCase
{
    // Violação de CHECK: 4025 no MariaDB, 3819 no MySQL 8.
    private const CODIGOS_CHECK = [4025, 3819];

    private const CODIGO_FK_FILHO = 1452;

    private const CODIGO_FK_PAI = 1451;

    private const CODIGO_DUPLICADO = 1062;

    private const AVISO = 'Exemplo — confira o prazo vigente na fonte oficial';

    private FabricaCadastro $cadastro;

    private FabricaFiscal $fiscal;

    private int $escritorioA;

    private int $escritorioB;

    private int $usuarioA;

    private int $empresaA;

    private int $empresaB;

    private int $tipoA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cadastro = new FabricaCadastro($this->conexao());
        $this->fiscal = new FabricaFiscal($this->conexao());

        $this->escritorioA = $this->cadastro->criarEscritorio();
        $this->escritorioB = $this->cadastro->criarEscritorio();
        $this->usuarioA = $this->cadastro->criarUsuario($this->escritorioA, 'COLABORADOR');
        $usuarioB = $this->cadastro->criarUsuario($this->escritorioB, 'ADMIN');
        $this->empresaA = $this->cadastro->criarEmpresa(
            $this->escritorioA,
            Cnpj::gerarFicticio(20000001),
            'Empresa A'
        );
        $this->empresaB = $this->cadastro->criarEmpresa(
            $this->escritorioB,
            Cnpj::gerarFicticio(20000002),
            'Empresa B',
            ['responsavel_id' => $usuarioB]
        );
        $this->tipoA = $this->fiscal->criarTipo($this->escritorioA);
    }

    public function testFabricaCriaObrigacaoValidaEmTodosOsStatus(): void
    {
        foreach (['PENDENTE', 'EM_ANDAMENTO', 'AGUARDANDO_CLIENTE', 'ENTREGUE', 'DISPENSADA'] as $indice => $status) {
            $id = $this->fiscal->criarObrigacao(
                $this->escritorioA,
                $this->empresaA,
                $this->tipoA,
                $this->usuarioA,
                [
                    'status' => $status,
                    'competencia' => sprintf('2026-%02d-01', $indice + 1),
                    'vencimento' => sprintf('2026-%02d-20', $indice + 2),
                ]
            );
            self::assertGreaterThan(0, $id);
        }
    }

    public function testFkCompostaRejeitaObrigacaoComEmpresaDeOutroEscritorio(): void
    {
        $this->esperarErro([self::CODIGO_FK_FILHO], function (): void {
            $this->fiscal->criarObrigacao(
                $this->escritorioA,
                $this->empresaB,
                $this->tipoA,
                $this->usuarioA
            );
        });
    }

    public function testFkCompostaRejeitaObrigacaoComTipoDeOutroEscritorio(): void
    {
        $tipoB = $this->fiscal->criarTipo($this->escritorioB);

        $this->esperarErro([self::CODIGO_FK_FILHO], function () use ($tipoB): void {
            $this->fiscal->criarObrigacao(
                $this->escritorioA,
                $this->empresaA,
                $tipoB,
                $this->usuarioA
            );
        });
    }

    public function testFkCompostaRejeitaVinculoEResponsavelDeOutroEscritorio(): void
    {
        $this->esperarErro([self::CODIGO_FK_FILHO], function (): void {
            $this->fiscal->criarVinculo($this->escritorioA, $this->empresaB, $this->tipoA);
        });

        $this->esperarErro([self::CODIGO_FK_FILHO], function (): void {
            $this->fiscal->criarObrigacao(
                $this->escritorioA,
                $this->empresaA,
                $this->tipoA,
                $this->usuarioA,
                ['responsavel_id' => $this->cadastro->criarUsuario($this->escritorioB, 'COLABORADOR')]
            );
        });
    }

    public function testCheckDeCompetenciaExigeDiaPrimeiro(): void
    {
        $this->esperarErro(self::CODIGOS_CHECK, function (): void {
            $this->fiscal->criarObrigacao(
                $this->escritorioA,
                $this->empresaA,
                $this->tipoA,
                $this->usuarioA,
                ['competencia' => '2026-01-15', 'vencimento' => '2026-02-20']
            );
        });
    }

    public function testCheckDeVencimentoNaoPodeSerAnteriorACompetencia(): void
    {
        $this->esperarErro(self::CODIGOS_CHECK, function (): void {
            $this->fiscal->criarObrigacao(
                $this->escritorioA,
                $this->empresaA,
                $this->tipoA,
                $this->usuarioA,
                ['competencia' => '2026-03-01', 'vencimento' => '2026-02-20']
            );
        });
    }

    public function testCheckDeEntregaExigeDataEAutorSomenteQuandoEntregue(): void
    {
        // ENTREGUE sem entregue_em e sem entregue_por (inserção direta, sem os padrões da fábrica).
        $this->esperarErro(self::CODIGOS_CHECK, function (): void {
            $this->inserirObrigacaoCrua('ENTREGUE', null, null);
        });

        // ENTREGUE só com a data.
        $this->esperarErro(self::CODIGOS_CHECK, function (): void {
            $this->inserirObrigacaoCrua('ENTREGUE', '2026-02-10 10:00:00', null);
        });

        // Status aberto não pode carregar dados de entrega.
        $this->esperarErro(self::CODIGOS_CHECK, function (): void {
            $this->inserirObrigacaoCrua('PENDENTE', '2026-02-10 10:00:00', $this->usuarioA);
        });
    }

    public function testEntregaCompletaEhAceita(): void
    {
        $id = $this->fiscal->criarObrigacao(
            $this->escritorioA,
            $this->empresaA,
            $this->tipoA,
            $this->usuarioA,
            ['status' => 'ENTREGUE', 'protocolo' => 'PROT-TESTE-1']
        );

        $stmt = $this->conexao()->prepare('SELECT entregue_em, entregue_por FROM obrigacoes WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $linha = $stmt->fetch();
        self::assertIsArray($linha);
        self::assertNotNull($linha['entregue_em']);
        self::assertSame($this->usuarioA, (int) $linha['entregue_por']);
    }

    public function testChaveUnicaImpedeCompetenciaDuplicada(): void
    {
        $this->fiscal->criarObrigacao($this->escritorioA, $this->empresaA, $this->tipoA, $this->usuarioA);

        $this->esperarErro([self::CODIGO_DUPLICADO], function (): void {
            $this->fiscal->criarObrigacao($this->escritorioA, $this->empresaA, $this->tipoA, $this->usuarioA);
        });

        // Outra competência, outro tipo e outra empresa do mesmo escritório continuam permitidos.
        $this->fiscal->criarObrigacao(
            $this->escritorioA,
            $this->empresaA,
            $this->tipoA,
            $this->usuarioA,
            ['competencia' => '2026-02-01', 'vencimento' => '2026-03-20']
        );
        $this->fiscal->criarObrigacao(
            $this->escritorioA,
            $this->empresaA,
            $this->fiscal->criarTipo($this->escritorioA),
            $this->usuarioA
        );
        $outraEmpresa = $this->cadastro->criarEmpresa(
            $this->escritorioA,
            Cnpj::gerarFicticio(20000003),
            'Empresa A2'
        );
        $this->fiscal->criarObrigacao($this->escritorioA, $outraEmpresa, $this->tipoA, $this->usuarioA);
        self::assertSame(4, $this->contar('obrigacoes', $this->escritorioA));
    }

    public function testExclusaoDeEmpresaComObrigacoesEBloqueada(): void
    {
        $this->fiscal->criarObrigacao($this->escritorioA, $this->empresaA, $this->tipoA, $this->usuarioA);

        $this->esperarErro([self::CODIGO_FK_PAI], function (): void {
            $this->excluirEmpresa($this->empresaA);
        });

        self::assertSame(1, $this->contar('empresas', $this->escritorioA));
        self::assertSame(1, $this->contar('obrigacoes', $this->escritorioA));
    }

    public function testExclusaoDeTipoComObrigacoesEBloqueada(): void
    {
        $this->fiscal->criarObrigacao($this->escritorioA, $this->empresaA, $this->tipoA, $this->usuarioA);

        $this->esperarErro([self::CODIGO_FK_PAI], function (): void {
            $stmt = $this->conexao()->prepare('DELETE FROM tipos_obrigacao WHERE id = :id');
            $stmt->execute(['id' => $this->tipoA]);
        });
    }

    public function testExclusaoDeEmpresaSemObrigacoesRemoveSomenteOsVinculos(): void
    {
        $this->fiscal->criarVinculo($this->escritorioA, $this->empresaA, $this->tipoA, $this->usuarioA);
        self::assertSame(1, $this->contar('empresa_tipos_obrigacao', $this->escritorioA));

        $this->excluirEmpresa($this->empresaA);

        self::assertSame(0, $this->contar('empresa_tipos_obrigacao', $this->escritorioA));
        self::assertSame(1, $this->contar('tipos_obrigacao', $this->escritorioA));
    }

    public function testChecksDoTipoDeObrigacao(): void
    {
        $this->esperarErro(self::CODIGOS_CHECK, function (): void {
            $this->fiscal->criarTipo($this->escritorioA, ['dia_vencimento' => 32]);
        });
        $this->esperarErro(self::CODIGOS_CHECK, function (): void {
            $this->fiscal->criarTipo($this->escritorioA, ['meses_apos_competencia' => 13]);
        });
        // MENSAL e TRIMESTRAL exigem o dia; ANUAL e EVENTUAL não.
        $this->esperarErro(self::CODIGOS_CHECK, function (): void {
            $this->fiscal->criarTipo($this->escritorioA, ['periodicidade' => 'TRIMESTRAL', 'dia_vencimento' => null]);
        });
        $this->fiscal->criarTipo($this->escritorioA, ['periodicidade' => 'ANUAL', 'dia_vencimento' => null]);
        $this->fiscal->criarTipo($this->escritorioA, ['periodicidade' => 'EVENTUAL', 'dia_vencimento' => null]);
    }

    public function testNomeDoTipoEUnicoPorEscritorio(): void
    {
        $this->fiscal->criarTipo($this->escritorioA, ['nome' => 'Nome repetido']);
        $this->fiscal->criarTipo($this->escritorioB, ['nome' => 'Nome repetido']);

        $this->esperarErro([self::CODIGO_DUPLICADO], function (): void {
            $this->fiscal->criarTipo($this->escritorioA, ['nome' => 'Nome repetido']);
        });
    }

    public function testSeedsFiscaisSaoValidosECobremTodosOsStatus(): void
    {
        $this->carregarSeeds();

        // ~8 tipos, todos marcados como exemplo na descrição.
        $tipos = $this->conexao()->query('SELECT descricao FROM tipos_obrigacao WHERE escritorio_id = 1');
        self::assertNotFalse($tipos);
        $descricoes = $tipos->fetchAll(\PDO::FETCH_COLUMN);
        self::assertCount(8, $descricoes);
        foreach ($descricoes as $descricao) {
            self::assertIsString($descricao);
            self::assertStringContainsString(self::AVISO, $descricao);
        }

        // Todos os status previstos aparecem.
        $consulta = $this->conexao()->query('SELECT DISTINCT status FROM obrigacoes');
        self::assertNotFalse($consulta);
        $status = $consulta->fetchAll(\PDO::FETCH_COLUMN);
        // Ordena no PHP: ORDER BY em ENUM ordena pela posição do valor, não pelo texto.
        sort($status);
        self::assertSame(
            ['AGUARDANDO_CLIENTE', 'DISPENSADA', 'EM_ANDAMENTO', 'ENTREGUE', 'PENDENTE'],
            $status
        );

        // Seis competências distintas, de ponta a ponta.
        self::assertSame(6, $this->escalar('SELECT COUNT(DISTINCT competencia) FROM obrigacoes'));

        // Há atrasadas ainda abertas e entregas feitas depois do vencimento.
        self::assertGreaterThan(0, $this->escalar(
            "SELECT COUNT(*) FROM obrigacoes
             WHERE status IN ('PENDENTE','EM_ANDAMENTO','AGUARDANDO_CLIENTE') AND vencimento < '2026-11-01'"
        ));
        self::assertGreaterThan(0, $this->escalar(
            "SELECT COUNT(*) FROM obrigacoes WHERE status = 'ENTREGUE' AND DATE(entregue_em) > vencimento"
        ));
        self::assertGreaterThan(0, $this->escalar(
            "SELECT COUNT(*) FROM obrigacoes WHERE status = 'ENTREGUE' AND DATE(entregue_em) <= vencimento"
        ));

        // Só existe fiscal no Exemplo Contábil; todo vínculo tem tipo e empresa do mesmo escritório.
        self::assertSame(0, $this->escalar('SELECT COUNT(*) FROM obrigacoes WHERE escritorio_id <> 1'));
        self::assertGreaterThan(0, $this->escalar('SELECT COUNT(*) FROM empresa_tipos_obrigacao'));
    }

    /**
     * Executa o bloco e exige um PDOException de integridade com um dos códigos de driver esperados.
     * Usa SAVEPOINT para a transação externa do teste continuar utilizável após o erro.
     *
     * @param list<int> $codigosEsperados
     */
    private function esperarErro(array $codigosEsperados, callable $acao): void
    {
        $this->conexao()->exec('SAVEPOINT antes_do_erro');
        try {
            $acao();
        } catch (PDOException $erro) {
            $this->conexao()->exec('ROLLBACK TO SAVEPOINT antes_do_erro');
            $codigo = (int) ($erro->errorInfo[1] ?? 0);
            self::assertContains($codigo, $codigosEsperados, $erro->getMessage());

            return;
        }

        self::fail('O banco deveria ter rejeitado a operação.');
    }

    private function inserirObrigacaoCrua(string $status, ?string $entreguem, ?int $entreguePor): void
    {
        $stmt = $this->conexao()->prepare(
            'INSERT INTO obrigacoes
                (escritorio_id, empresa_id, tipo_obrigacao_id, competencia, vencimento,
                 status, entregue_em, entregue_por, criado_por)
             VALUES
                (:escritorio_id, :empresa_id, :tipo_obrigacao_id, :competencia, :vencimento,
                 :status, :entregue_em, :entregue_por, :criado_por)'
        );
        $stmt->execute([
            'escritorio_id' => $this->escritorioA,
            'empresa_id' => $this->empresaA,
            'tipo_obrigacao_id' => $this->tipoA,
            'competencia' => '2026-01-01',
            'vencimento' => '2026-02-20',
            'status' => $status,
            'entregue_em' => $entreguem,
            'entregue_por' => $entreguePor,
            'criado_por' => $this->usuarioA,
        ]);
    }

    private function excluirEmpresa(int $empresaId): void
    {
        $stmt = $this->conexao()->prepare('DELETE FROM empresas WHERE id = :id');
        $stmt->execute(['id' => $empresaId]);
    }

    private function contar(string $tabela, int $escritorioId): int
    {
        // Nome da tabela vem sempre de literais deste arquivo, nunca de entrada externa.
        $stmt = $this->conexao()->prepare('SELECT COUNT(*) FROM ' . $tabela . ' WHERE escritorio_id = :e');
        $stmt->execute(['e' => $escritorioId]);

        return (int) $stmt->fetchColumn();
    }

    private function escalar(string $sql): int
    {
        $resultado = $this->conexao()->query($sql);
        self::assertNotFalse($resultado);

        return (int) $resultado->fetchColumn();
    }

    /**
     * O banco de teste nasce sem seeds; aqui eles rodam dentro da transação do teste
     * (só INSERTs), então o rollback do tearDown os desfaz.
     */
    private function carregarSeeds(): void
    {
        $raiz = dirname(__DIR__, 3);
        require_once $raiz . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'db.php';

        // Escritórios de setUp ocupam ids; os seeds usam ids fixos 1.., então limpa o que o setUp criou.
        $this->conexao()->exec('DELETE FROM tipos_obrigacao');
        $this->conexao()->exec('DELETE FROM empresas');
        $this->conexao()->exec('DELETE FROM usuarios');
        $this->conexao()->exec('DELETE FROM escritorios');

        $seeds = arquivosSql($raiz . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'seeds');
        executarArquivosSql($this->conexao(), $seeds);
    }
}
