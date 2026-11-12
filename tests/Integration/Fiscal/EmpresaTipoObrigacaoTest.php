<?php

declare(strict_types=1);

namespace Tests\Integration\Fiscal;

use App\Core\Config;
use App\Core\UsuarioLogado;
use App\Exceptions\ConflitoException;
use App\Exceptions\NaoEncontradoException;
use App\Models\Fiscal\EmpresaTipoObrigacao;
use App\Services\Fiscal\TipoObrigacaoService;
use App\Support\Cnpj;
use PDO;
use PDOException;
use Tests\Integration\IntegrationTestCase;
use Tests\Support\FabricaCadastro;
use Tests\Support\FabricaFiscal;

/**
 * Vínculos empresa × tipo e as regras do TipoObrigacaoService.
 *
 * Esta classe NÃO usa a transação externa do IntegrationTestCase: dentro de uma transação
 * aberta, Database::transacao apenas executa o callback, e assim não daria para provar o
 * rollback do service. Os dados criados são apagados no tearDown.
 */
final class EmpresaTipoObrigacaoTest extends IntegrationTestCase
{
    private const USUARIO_INEXISTENTE = 2_000_000_000;

    /**
     * Script do processo concorrente (enviado pelo stdin do php, sem arquivo extra).
     * Usa o mesmo service da aplicação, com conexão própria ao banco.
     */
    private const SCRIPT_CONCORRENTE = <<<'PHP'
        <?php
        declare(strict_types=1);
        require 'vendor/autoload.php';
        if (is_file('.env')) {
            \App\Core\Env::carregar('.env');
        }
        \App\Core\Config::definir(require 'config/app.php');
        $conexao = \App\Core\Database::conexao();
        // Script lido do stdin: as constantes STDOUT/STDERR não existem nesse modo.
        if ($conexao->query('SELECT DATABASE()')->fetchColumn() !== 'tributo_test') {
            file_put_contents('php://stderr', 'Recusado: a conexão não é do banco de testes.');
            exit(2);
        }
        $saida = fopen('php://stdout', 'w');
        fwrite($saida, $conexao->query('SELECT CONNECTION_ID()')->fetchColumn() . PHP_EOL);
        fflush($saida);
        $dados = json_decode((string) getenv('FI02_CONCORRENCIA'), true, 512, JSON_THROW_ON_ERROR);
        $vinculos = [];
        foreach ($dados['vinculos'] as $tipo => $responsavel) {
            $vinculos[(int) $tipo] = $responsavel === null ? null : (int) $responsavel;
        }
        $usuario = new \App\Core\UsuarioLogado(
            (int) $dados['usuario'], (int) $dados['escritorio'], 'COLABORADOR', null, 'Concorrente', []
        );
        \App\Services\Fiscal\TipoObrigacaoService::padrao()
            ->substituirVinculos($usuario, (int) $dados['empresa'], $vinculos);
        PHP;

    /** @var resource|null */
    private $processoConcorrente = null;

    /** @var array<int, resource> */
    private array $pipesConcorrente = [];

    private FabricaCadastro $cadastro;

    private FabricaFiscal $fiscal;

    private EmpresaTipoObrigacao $vinculos;

    private TipoObrigacaoService $servico;

    /** @var list<int> */
    private array $escritoriosCriados = [];

    private int $escritorioA;

    private int $escritorioB;

    private int $colaboradorA;

    private int $colaboradorB;

    private int $empresaA;

    private int $empresaB;

    private int $tipoMensal;

    private int $tipoTrimestral;

    private int $tipoInativo;

    private int $tipoB;

    protected function iniciarTransacaoExterna(): bool
    {
        return false;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->cadastro = new FabricaCadastro($this->conexao());
        $this->fiscal = new FabricaFiscal($this->conexao());
        $this->vinculos = new EmpresaTipoObrigacao();
        $this->servico = TipoObrigacaoService::padrao();

        $this->escritorioA = $this->novoEscritorio();
        $this->escritorioB = $this->novoEscritorio();
        $this->colaboradorA = $this->cadastro->criarUsuario($this->escritorioA, 'COLABORADOR');
        $this->colaboradorB = $this->cadastro->criarUsuario($this->escritorioB, 'COLABORADOR');
        $this->empresaA = $this->cadastro->criarEmpresa(
            $this->escritorioA,
            Cnpj::gerarFicticio(random_int(1, 99_999_999)),
            'Empresa A'
        );
        $this->empresaB = $this->cadastro->criarEmpresa(
            $this->escritorioB,
            Cnpj::gerarFicticio(random_int(1, 99_999_999)),
            'Empresa B'
        );
        $this->tipoMensal = $this->fiscal->criarTipo($this->escritorioA, ['nome' => 'B mensal']);
        $this->tipoTrimestral = $this->fiscal->criarTipo($this->escritorioA, [
            'nome' => 'A trimestral',
            'periodicidade' => 'TRIMESTRAL',
        ]);
        $this->tipoInativo = $this->fiscal->criarTipo($this->escritorioA, ['nome' => 'C inativo', 'ativo' => 0]);
        $this->tipoB = $this->fiscal->criarTipo($this->escritorioB, ['nome' => 'Tipo do B']);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        // Se o teste de concorrência falhou no meio, o processo filho não pode ficar vivo.
        if (is_resource($this->processoConcorrente)) {
            proc_terminate($this->processoConcorrente);
            proc_close($this->processoConcorrente);
            $this->processoConcorrente = null;
        }
        foreach ($this->escritoriosCriados as $escritorioId) {
            $this->limparEscritorio($escritorioId);
        }
    }

    public function testListaTiposAtivosEInativosJaVinculadosComMarcacao(): void
    {
        $outroInativo = $this->fiscal->criarTipo($this->escritorioA, ['nome' => 'D inativo vinculado', 'ativo' => 0]);
        $this->fiscal->criarVinculo($this->escritorioA, $this->empresaA, $this->tipoMensal, $this->colaboradorA);
        $this->fiscal->criarVinculo($this->escritorioA, $this->empresaA, $outroInativo);

        $linhas = $this->vinculos->listarParaEmpresa($this->escritorioA, $this->empresaA);

        // Ordem por nome; o inativo não vinculado fica de fora; nada do escritório B aparece.
        self::assertSame(
            [$this->tipoTrimestral, $this->tipoMensal, $outroInativo],
            array_map(static fn (array $linha): int => (int) $linha['id'], $linhas)
        );
        self::assertSame([0, 1, 1], array_map(static fn (array $l): int => (int) $l['vinculado'], $linhas));
        self::assertSame($this->colaboradorA, (int) $linhas[1]['responsavel_padrao_id']);
        self::assertNull($linhas[2]['responsavel_padrao_id']);
    }

    public function testSubstituirAdicionaMantemRemoveEAtualizaResponsavelComAuditoria(): void
    {
        $this->fiscal->criarVinculo($this->escritorioA, $this->empresaA, $this->tipoMensal);
        $this->fiscal->criarVinculo($this->escritorioA, $this->empresaA, $this->tipoInativo);

        // Mantém o mensal (agora com responsável), inclui o trimestral e remove o inativo.
        $this->servico->substituirVinculos($this->usuario(), $this->empresaA, [
            $this->tipoMensal => $this->colaboradorA,
            $this->tipoTrimestral => null,
        ]);

        self::assertSame(
            [$this->tipoMensal => $this->colaboradorA, $this->tipoTrimestral => null],
            $this->vinculos->listarVinculos($this->escritorioA, $this->empresaA)
        );
        $log = $this->ultimoLog('empresa', $this->empresaA);
        self::assertSame('tipos_obrigacao', $log['acao']);
        self::assertSame([
            'antes' => [$this->vinculo($this->tipoMensal, null), $this->vinculo($this->tipoInativo, null)],
            'depois' => [$this->vinculo($this->tipoMensal, $this->colaboradorA), $this->vinculo($this->tipoTrimestral, null)],
        ], $this->detalhes($log));
    }

    public function testTrocaSomenteDoResponsavelFicaRegistradaNaAuditoria(): void
    {
        $outroColaborador = $this->cadastro->criarUsuario($this->escritorioA, 'COLABORADOR');
        $this->fiscal->criarVinculo($this->escritorioA, $this->empresaA, $this->tipoMensal, $this->colaboradorA);

        // Mesmo tipo, outro responsável.
        $this->servico->substituirVinculos($this->usuario(), $this->empresaA, [$this->tipoMensal => $outroColaborador]);

        self::assertSame(
            [$this->tipoMensal => $outroColaborador],
            $this->vinculos->listarVinculos($this->escritorioA, $this->empresaA)
        );
        self::assertSame([
            'antes' => [$this->vinculo($this->tipoMensal, $this->colaboradorA)],
            'depois' => [$this->vinculo($this->tipoMensal, $outroColaborador)],
        ], $this->detalhes($this->ultimoLog('empresa', $this->empresaA)));

        // Mesmo tipo, responsável removido: o "null" precisa aparecer explicitamente.
        $this->servico->substituirVinculos($this->usuario(), $this->empresaA, [$this->tipoMensal => null]);

        self::assertSame(
            [$this->tipoMensal => null],
            $this->vinculos->listarVinculos($this->escritorioA, $this->empresaA)
        );
        $detalhes = $this->detalhes($this->ultimoLog('empresa', $this->empresaA));
        self::assertSame([
            'antes' => [$this->vinculo($this->tipoMensal, $outroColaborador)],
            'depois' => [$this->vinculo($this->tipoMensal, null)],
        ], $detalhes);
        self::assertArrayHasKey('responsavel_padrao_id', $detalhes['depois'][0]);
    }

    public function testSubstituirComConjuntoVazioRemoveTodosOsVinculos(): void
    {
        $this->fiscal->criarVinculo($this->escritorioA, $this->empresaA, $this->tipoMensal);
        $this->fiscal->criarVinculo($this->escritorioA, $this->empresaA, $this->tipoTrimestral);

        $this->servico->substituirVinculos($this->usuario(), $this->empresaA, []);

        self::assertSame([], $this->vinculos->listarVinculos($this->escritorioA, $this->empresaA));
    }

    public function testInativoJaVinculadoPodeSerMantidoMasNaoIncluido(): void
    {
        self::assertArrayHasKey(
            'tipos',
            $this->servico->errosDosVinculos($this->usuario(), $this->empresaA, [$this->tipoInativo => null])
        );

        $this->fiscal->criarVinculo($this->escritorioA, $this->empresaA, $this->tipoInativo);
        self::assertSame(
            [],
            $this->servico->errosDosVinculos($this->usuario(), $this->empresaA, [$this->tipoInativo => null])
        );
    }

    public function testRejeitaTipoEResponsavelDeOutroEscritorioOuInvalido(): void
    {
        $usuario = $this->usuario();
        $clienteA = $this->cadastro->criarUsuario($this->escritorioA, 'CLIENTE', $this->empresaA);
        $inativoA = $this->cadastro->criarUsuario($this->escritorioA, 'COLABORADOR', null, false);

        self::assertArrayHasKey('tipos', $this->servico->errosDosVinculos($usuario, $this->empresaA, [
            $this->tipoB => null,
        ]));
        foreach ([$this->colaboradorB, $clienteA, $inativoA, self::USUARIO_INEXISTENTE] as $responsavel) {
            self::assertArrayHasKey('responsavel_padrao', $this->servico->errosDosVinculos(
                $usuario,
                $this->empresaA,
                [$this->tipoMensal => $responsavel]
            ));
        }

        // O service revalida por conta própria e não grava nada.
        try {
            $this->servico->substituirVinculos($usuario, $this->empresaA, [$this->tipoB => null]);
            self::fail('Um tipo de outro escritório não pode ser vinculado.');
        } catch (\InvalidArgumentException) {
            self::assertSame([], $this->vinculos->listarVinculos($this->escritorioA, $this->empresaA));
        }
    }

    public function testEmpresaDeOutroEscritorioGeraNaoEncontrado(): void
    {
        $this->fiscal->criarVinculo($this->escritorioB, $this->empresaB, $this->tipoB);

        try {
            $this->servico->substituirVinculos($this->usuario(), $this->empresaB, []);
            self::fail('A empresa de outro escritório deveria gerar 404.');
        } catch (NaoEncontradoException) {
            self::assertSame(
                [$this->tipoB => null],
                $this->vinculos->listarVinculos($this->escritorioB, $this->empresaB)
            );
        }
        self::assertSame([], $this->vinculos->listarVinculos($this->escritorioA, $this->empresaB));
    }

    public function testFalhaNaAuditoriaDesfazASubstituicaoInteira(): void
    {
        $this->fiscal->criarVinculo($this->escritorioA, $this->empresaA, $this->tipoMensal, $this->colaboradorA);

        try {
            $this->servico->substituirVinculos(
                $this->usuario(self::USUARIO_INEXISTENTE),
                $this->empresaA,
                [$this->tipoTrimestral => null]
            );
            self::fail('A auditoria com usuário inexistente deveria falhar.');
        } catch (PDOException) {
            self::assertFalse($this->conexao()->inTransaction());
        }

        self::assertSame(
            [$this->tipoMensal => $this->colaboradorA],
            $this->vinculos->listarVinculos($this->escritorioA, $this->empresaA)
        );
    }

    /**
     * Concorrência real: duas conexões, cada uma com sua transação, substituindo os
     * vínculos da MESMA empresa ao mesmo tempo.
     *
     * 1. Este processo abre a transação e faz a 1ª substituição, sem commit: o bloqueio fica preso.
     * 2. Um processo PHP filho, com outra conexão, inicia a 2ª substituição pelo mesmo service.
     * 3. Uma terceira conexão confirma em INNODB_TRX que o filho está em LOCK WAIT,
     *    parado justamente no SELECT ... FOR UPDATE da empresa.
     * 4. Este processo faz commit; o filho continua, lê o conjunto já gravado e termina.
     *
     * Sem o bloqueio, o filho leria o conjunto inicial e o resultado final teria o
     * tipo trimestral da 1ª substituição, que ele não pediu (o defeito apontado na revisão).
     */
    public function testSubstituicoesConcorrentesDaMesmaEmpresaSaoSerializadas(): void
    {
        $this->fiscal->criarVinculo($this->escritorioA, $this->empresaA, $this->tipoMensal);
        $this->fiscal->criarVinculo($this->escritorioA, $this->empresaA, $this->tipoInativo);
        $conexao = $this->conexao();

        $conexao->beginTransaction();
        try {
            $this->servico->substituirVinculos($this->usuario(), $this->empresaA, [$this->tipoTrimestral => null]);
            $conexaoFilho = $this->iniciarSubstituicaoConcorrente([$this->tipoMensal => $this->colaboradorA]);
            $this->esperarBloqueioNaEmpresa($conexaoFilho);
            $conexao->commit();
        } finally {
            if ($conexao->inTransaction()) {
                $conexao->rollBack();
            }
        }

        $resultado = $this->concluirProcessoConcorrente();
        self::assertSame(0, $resultado['codigo'], $resultado['saida']);

        // O conjunto final é exatamente o da última substituição executada.
        self::assertSame(
            [$this->tipoMensal => $this->colaboradorA],
            $this->vinculos->listarVinculos($this->escritorioA, $this->empresaA)
        );

        // A auditoria da 2ª substituição registra como "antes" o resultado da 1ª.
        self::assertSame([
            [
                'antes' => [$this->vinculo($this->tipoMensal, null), $this->vinculo($this->tipoInativo, null)],
                'depois' => [$this->vinculo($this->tipoTrimestral, null)],
            ],
            [
                'antes' => [$this->vinculo($this->tipoTrimestral, null)],
                'depois' => [$this->vinculo($this->tipoMensal, $this->colaboradorA)],
            ],
        ], $this->auditoriasDosVinculos());
    }

    public function testServicoDeTiposAuditaEDesfazEmCasoDeFalha(): void
    {
        $id = $this->servico->criar($this->usuario(), $this->dadosTipo('Tipo auditado'));
        self::assertSame('criar', $this->ultimoLog('tipo_obrigacao', $id)['acao']);

        $this->servico->atualizar($this->usuario(), $id, $this->dadosTipo('Tipo auditado editado'));
        self::assertSame('editar', $this->ultimoLog('tipo_obrigacao', $id)['acao']);

        try {
            $this->servico->criar($this->usuario(self::USUARIO_INEXISTENTE), $this->dadosTipo('Tipo sem auditoria'));
            self::fail('A auditoria com usuário inexistente deveria falhar.');
        } catch (PDOException) {
            self::assertSame(0, $this->contar(
                'SELECT COUNT(*) FROM tipos_obrigacao WHERE escritorio_id = :e AND nome = :n',
                ['e' => $this->escritorioA, 'n' => 'Tipo sem auditoria']
            ));
        }

        $this->servico->excluir($this->usuario(), $id);
        self::assertSame('excluir', $this->ultimoLog('tipo_obrigacao', $id)['acao']);
    }

    public function testExcluirTipoComObrigacaoGeraConflitoEMantemTudo(): void
    {
        $this->fiscal->criarVinculo($this->escritorioA, $this->empresaA, $this->tipoMensal);
        $this->fiscal->criarObrigacao($this->escritorioA, $this->empresaA, $this->tipoMensal, $this->colaboradorA);

        try {
            $this->servico->excluir($this->usuario(), $this->tipoMensal);
            self::fail('Excluir tipo com obrigações deveria gerar conflito.');
        } catch (ConflitoException $erro) {
            self::assertStringContainsString('inative o tipo', $erro->getMessage());
        }

        self::assertSame(1, $this->contar(
            'SELECT COUNT(*) FROM tipos_obrigacao WHERE id = :id',
            ['id' => $this->tipoMensal]
        ));
        self::assertSame(
            [$this->tipoMensal => null],
            $this->vinculos->listarVinculos($this->escritorioA, $this->empresaA)
        );
    }

    public function testExcluirTipoSemObrigacoesRemoveVinculosEmCascata(): void
    {
        $this->fiscal->criarVinculo($this->escritorioA, $this->empresaA, $this->tipoTrimestral);

        $this->servico->excluir($this->usuario(), $this->tipoTrimestral);

        self::assertSame([], $this->vinculos->listarVinculos($this->escritorioA, $this->empresaA));
    }

    public function testServicoNaoAlteraNemExcluiTipoDeOutroEscritorio(): void
    {
        foreach (['atualizar', 'excluir'] as $operacao) {
            try {
                if ($operacao === 'atualizar') {
                    $this->servico->atualizar($this->usuario(), $this->tipoB, $this->dadosTipo('Invasão'));
                } else {
                    $this->servico->excluir($this->usuario(), $this->tipoB);
                }
                self::fail('Tipo de outro escritório deveria gerar 404 em ' . $operacao . '.');
            } catch (NaoEncontradoException) {
                $this->addToAssertionCount(1);
            }
        }

        self::assertSame(1, $this->contar(
            "SELECT COUNT(*) FROM tipos_obrigacao WHERE id = :id AND nome = 'Tipo do B'",
            ['id' => $this->tipoB]
        ));
    }

    /**
     * @param array<int, int|null> $vinculos
     * @return int ID da conexão MySQL do processo filho
     */
    private function iniciarSubstituicaoConcorrente(array $vinculos): int
    {
        $dados = json_encode([
            'usuario' => $this->colaboradorA,
            'escritorio' => $this->escritorioA,
            'empresa' => $this->empresaA,
            'vinculos' => $vinculos,
        ], JSON_THROW_ON_ERROR);
        $processo = proc_open(
            [PHP_BINARY],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            dirname(__DIR__, 3),
            array_merge(getenv(), ['APP_ENV' => 'testing', 'FI02_CONCORRENCIA' => $dados])
        );
        self::assertIsResource($processo);
        $this->processoConcorrente = $processo;
        $this->pipesConcorrente = $pipes;

        fwrite($pipes[0], self::SCRIPT_CONCORRENTE);
        fclose($pipes[0]);
        $linha = fgets($pipes[1]);
        if (!is_string($linha)) {
            // Só aqui o stderr é lido: a leitura espera o filho terminar.
            self::fail('O processo concorrente não informou a conexão: ' . stream_get_contents($pipes[2]));
        }

        return (int) trim($linha);
    }

    /**
     * Espera (no máximo 10 s) o filho ficar em LOCK WAIT no FOR UPDATE da tabela empresas.
     */
    private function esperarBloqueioNaEmpresa(int $conexaoFilho): void
    {
        $monitor = $this->conexaoMonitora();
        $consulta = $monitor->prepare(
            'SELECT trx_state, trx_query FROM information_schema.INNODB_TRX WHERE trx_mysql_thread_id = :id'
        );

        // A prova vem do próprio banco: a transação do filho em LOCK WAIT, na consulta FOR UPDATE.
        for ($tentativa = 0; $tentativa < 100; $tentativa++) {
            $consulta->execute(['id' => $conexaoFilho]);
            $transacao = $consulta->fetch();
            if (is_array($transacao) && $transacao['trx_state'] === 'LOCK WAIT') {
                $sql = (string) $transacao['trx_query'];
                self::assertStringContainsString('FROM empresas', $sql);
                self::assertStringContainsString('FOR UPDATE', $sql);

                return;
            }
            usleep(100_000);
        }

        // Encerra o filho antes de ler a saída, para a leitura não ficar presa.
        self::assertIsResource($this->processoConcorrente);
        proc_terminate($this->processoConcorrente);
        self::fail(
            'O processo concorrente não ficou esperando o bloqueio da empresa. Saída: '
            . $this->concluirProcessoConcorrente()['saida']
        );
    }

    /**
     * @return array{codigo: int, saida: string}
     */
    private function concluirProcessoConcorrente(): array
    {
        self::assertIsResource($this->processoConcorrente);
        $saida = stream_get_contents($this->pipesConcorrente[1]) . stream_get_contents($this->pipesConcorrente[2]);
        fclose($this->pipesConcorrente[1]);
        fclose($this->pipesConcorrente[2]);
        $codigo = proc_close($this->processoConcorrente);
        $this->processoConcorrente = null;

        return ['codigo' => $codigo, 'saida' => (string) $saida];
    }

    /**
     * Conexão independente, só para observar as transações das outras duas.
     */
    private function conexaoMonitora(): PDO
    {
        return new PDO(
            sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                (string) Config::obter('db.host'),
                (int) Config::obter('db.porta'),
                (string) Config::obter('db.banco')
            ),
            (string) Config::obter('db.usuario'),
            (string) Config::obter('db.senha'),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function auditoriasDosVinculos(): array
    {
        $stmt = $this->conexao()->prepare(
            "SELECT detalhes FROM logs_atividade
             WHERE escritorio_id = :e AND entidade = 'empresa' AND entidade_id = :empresa
               AND acao = 'tipos_obrigacao'
             ORDER BY id ASC"
        );
        $stmt->execute(['e' => $this->escritorioA, 'empresa' => $this->empresaA]);

        return array_map(
            fn (mixed $detalhes): array => $this->detalhes(['detalhes' => $detalhes]),
            $stmt->fetchAll(PDO::FETCH_COLUMN)
        );
    }

    /**
     * Formato de cada vínculo nos detalhes da auditoria.
     *
     * @return array{tipo_obrigacao_id: int, responsavel_padrao_id: int|null}
     */
    private function vinculo(int $tipoId, ?int $responsavelId): array
    {
        return ['tipo_obrigacao_id' => $tipoId, 'responsavel_padrao_id' => $responsavelId];
    }

    /**
     * JSON de detalhes de um log, decodificado como foi gravado (sem reordenar).
     *
     * @param array<string, mixed> $log
     * @return array<string, mixed>
     */
    private function detalhes(array $log): array
    {
        $detalhes = json_decode((string) $log['detalhes'], true);
        self::assertIsArray($detalhes);

        return $detalhes;
    }

    private function novoEscritorio(): int
    {
        $id = $this->cadastro->criarEscritorio();
        $this->escritoriosCriados[] = $id;

        return $id;
    }

    private function usuario(?int $id = null): UsuarioLogado
    {
        return new UsuarioLogado($id ?? $this->colaboradorA, $this->escritorioA, 'COLABORADOR', null, 'Ana', []);
    }

    /**
     * @return array<string, mixed>
     */
    private function dadosTipo(string $nome): array
    {
        return [
            'nome' => $nome,
            'descricao' => null,
            'esfera' => 'FEDERAL',
            'periodicidade' => 'MENSAL',
            'dia_vencimento' => 15,
            'meses_apos_competencia' => 1,
            'ativo' => 1,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function ultimoLog(string $entidade, int $id): array
    {
        $stmt = $this->conexao()->prepare(
            'SELECT acao, detalhes FROM logs_atividade
             WHERE escritorio_id = :e AND entidade = :entidade AND entidade_id = :id
             ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute(['e' => $this->escritorioA, 'entidade' => $entidade, 'id' => $id]);
        $log = $stmt->fetch();
        self::assertIsArray($log);

        return $log;
    }

    /**
     * @param array<string, int|string> $parametros
     */
    private function contar(string $sql, array $parametros): int
    {
        $stmt = $this->conexao()->prepare($sql);
        $stmt->execute($parametros);

        return (int) $stmt->fetchColumn();
    }

    private function limparEscritorio(int $escritorioId): void
    {
        // Ordem inversa das dependências; CLIENTE referencia empresa e empresa referencia usuário.
        foreach ([
            'DELETE FROM logs_atividade WHERE escritorio_id = :e',
            'DELETE FROM obrigacoes WHERE escritorio_id = :e',
            'DELETE FROM empresa_tipos_obrigacao WHERE escritorio_id = :e',
            'DELETE FROM tipos_obrigacao WHERE escritorio_id = :e',
            "DELETE FROM usuarios WHERE escritorio_id = :e AND perfil = 'CLIENTE'",
            'DELETE FROM empresas WHERE escritorio_id = :e',
            'DELETE FROM usuarios WHERE escritorio_id = :e',
            'DELETE FROM escritorios WHERE id = :e',
        ] as $sql) {
            $this->conexao()->prepare($sql)->execute(['e' => $escritorioId]);
        }
    }
}
