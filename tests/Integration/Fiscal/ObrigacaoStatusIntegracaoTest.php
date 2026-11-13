<?php

declare(strict_types=1);

namespace Tests\Integration\Fiscal;

use App\Core\UsuarioLogado;
use App\Exceptions\AcessoNegadoException;
use App\Exceptions\ConflitoException;
use App\Exceptions\NaoEncontradoException;
use App\Models\Fiscal\Obrigacao;
use App\Models\Nucleo\LogAtividade;
use App\Services\Fiscal\ObrigacaoStatusService;
use App\Services\Nucleo\AuditoriaService;
use App\Support\Cnpj;
use App\Support\RelogioFixo;
use DateTimeImmutable;
use PDOException;
use Tests\Integration\IntegrationTestCase;
use Tests\Support\FabricaCadastro;
use Tests\Support\FabricaFiscal;

/**
 * Mudança de status com auditoria e histórico (FI04). "Agora" é sempre 10/03/2026 14:30:00.
 *
 * Esta classe NÃO usa a transação externa do IntegrationTestCase (mesmo recurso usado na FI02):
 * dentro de uma transação aberta, Database::transacao apenas executa o callback, e assim não
 * daria para provar o rollback. Os dados criados são apagados no tearDown.
 */
final class ObrigacaoStatusIntegracaoTest extends IntegrationTestCase
{
    private const AGORA = '2026-03-10 14:30:00';

    private const USUARIO_INEXISTENTE = 2_000_000_000;

    private ObrigacaoStatusService $servico;

    private Obrigacao $obrigacoes;

    private FabricaCadastro $cadastro;

    private FabricaFiscal $fiscal;

    /** @var list<int> */
    private array $escritoriosCriados = [];

    private int $escritorioA;

    private int $escritorioB;

    private int $adminA;

    private int $colaboradorA;

    private int $adminB;

    private int $empresaA;

    protected function iniciarTransacaoExterna(): bool
    {
        return false;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $relogio = new RelogioFixo(new DateTimeImmutable(self::AGORA));
        $this->obrigacoes = new Obrigacao($relogio);
        $this->servico = new ObrigacaoStatusService($this->obrigacoes, new AuditoriaService(new LogAtividade()), $relogio);
        $this->cadastro = new FabricaCadastro($this->conexao());
        $this->fiscal = new FabricaFiscal($this->conexao());

        $this->escritorioA = $this->novoEscritorio();
        $this->escritorioB = $this->novoEscritorio();
        $this->adminA = $this->cadastro->criarUsuario($this->escritorioA, 'ADMIN');
        $this->colaboradorA = $this->cadastro->criarUsuario($this->escritorioA, 'COLABORADOR');
        $this->adminB = $this->cadastro->criarUsuario($this->escritorioB, 'ADMIN');
        $this->empresaA = $this->cadastro->criarEmpresa(
            $this->escritorioA,
            Cnpj::gerarFicticio(random_int(1, 99_999_999)),
            'Empresa Status'
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        foreach ($this->escritoriosCriados as $escritorioId) {
            $this->limparEscritorio($escritorioId);
        }
    }

    public function testEntregaGravaDataUsuarioProtocoloEAuditoria(): void
    {
        $id = $this->nova();

        $this->servico->mudarStatus($this->colaborador(), $id, $this->mudanca('ENTREGUE', null, 'REC-2026-001'), 1);

        $obrigacao = $this->registro($id);
        self::assertSame('ENTREGUE', $obrigacao['status']);
        self::assertSame(self::AGORA, $obrigacao['entregue_em']);
        self::assertSame($this->colaboradorA, (int) $obrigacao['entregue_por']);
        self::assertSame('REC-2026-001', $obrigacao['protocolo']);
        self::assertSame(2, (int) $obrigacao['versao']);

        $logs = $this->logs($id);
        self::assertCount(1, $logs);
        self::assertSame('status:ENTREGUE', $logs[0]['acao']);
        self::assertSame($this->colaboradorA, (int) $logs[0]['usuario_id']);
        self::assertSame(
            ['de' => 'PENDENTE', 'para' => 'ENTREGUE', 'justificativa' => null, 'protocolo' => 'REC-2026-001'],
            $this->detalhes($logs[0])
        );
    }

    public function testReaberturaLimpaEntregaEMantemProtocolo(): void
    {
        $id = $this->nova(['status' => 'ENTREGUE', 'protocolo' => 'REC-ANTIGO']);

        $this->servico->mudarStatus($this->admin(), $id, $this->mudanca('EM_ANDAMENTO', 'Guia retificada'), 1);

        $obrigacao = $this->registro($id);
        self::assertSame('EM_ANDAMENTO', $obrigacao['status']);
        self::assertNull($obrigacao['entregue_em']);
        self::assertNull($obrigacao['entregue_por']);
        // D4: o protocolo continua no cadastro ao sair de ENTREGUE.
        self::assertSame('REC-ANTIGO', $obrigacao['protocolo']);
        self::assertSame(
            ['de' => 'ENTREGUE', 'para' => 'EM_ANDAMENTO', 'justificativa' => 'Guia retificada', 'protocolo' => null],
            $this->detalhes($this->logs($id)[0])
        );
    }

    public function testNovaEntregaSemProtocoloMantemOAnteriorMasOLogRegistraNulo(): void
    {
        $id = $this->nova(['status' => 'ENTREGUE', 'protocolo' => 'REC-ANTIGO']);
        $this->servico->mudarStatus($this->admin(), $id, $this->mudanca('EM_ANDAMENTO', 'Reabrir'), 1);

        $this->servico->mudarStatus($this->colaborador(), $id, $this->mudanca('ENTREGUE'), 2);

        $obrigacao = $this->registro($id);
        self::assertSame('ENTREGUE', $obrigacao['status']);
        self::assertSame('REC-ANTIGO', $obrigacao['protocolo']);
        self::assertSame(self::AGORA, $obrigacao['entregue_em']);
        // D9: o evento guarda só o que foi enviado naquela requisição.
        $ultimo = $this->logs($id)[1];
        self::assertSame('status:ENTREGUE', $ultimo['acao']);
        self::assertNull($this->detalhes($ultimo)['protocolo']);

        // Um protocolo novo substitui o anterior.
        $this->servico->mudarStatus($this->admin(), $id, $this->mudanca('EM_ANDAMENTO', 'Reabrir de novo'), 3);
        $this->servico->mudarStatus($this->admin(), $id, $this->mudanca('ENTREGUE', null, 'REC-NOVO'), 4);
        self::assertSame('REC-NOVO', $this->registro($id)['protocolo']);
    }

    public function testDispensaGuardaAJustificativa(): void
    {
        $id = $this->nova(['status' => 'AGUARDANDO_CLIENTE']);

        $this->servico->mudarStatus($this->colaborador(), $id, $this->mudanca('DISPENSADA', 'Empresa sem movimento'), 1);

        self::assertSame('DISPENSADA', $this->registro($id)['status']);
        self::assertSame(
            ['de' => 'AGUARDANDO_CLIENTE', 'para' => 'DISPENSADA', 'justificativa' => 'Empresa sem movimento', 'protocolo' => null],
            $this->detalhes($this->logs($id)[0])
        );
    }

    public function testVersaoDesatualizadaNaoMudaNadaNemGravaLog(): void
    {
        $id = $this->nova();
        $this->servico->mudarStatus($this->colaborador(), $id, $this->mudanca('EM_ANDAMENTO'), 1);

        try {
            $this->servico->mudarStatus($this->colaborador(), $id, $this->mudanca('ENTREGUE'), 1);
            self::fail('A versão 1 já estava desatualizada.');
        } catch (ConflitoException $erro) {
            self::assertSame('O registro foi alterado por outra operação.', $erro->getMessage());
        }

        $obrigacao = $this->registro($id);
        self::assertSame('EM_ANDAMENTO', $obrigacao['status']);
        self::assertSame(2, (int) $obrigacao['versao']);
        self::assertNull($obrigacao['entregue_em']);
        self::assertCount(1, $this->logs($id));
    }

    public function testModelRecusaVersaoDesatualizadaNoUpdate(): void
    {
        $id = $this->nova();

        $this->expectException(ConflitoException::class);
        $this->obrigacoes->mudarStatus($this->escritorioA, $id, [
            'status' => 'EM_ANDAMENTO',
            'entregue_em' => null,
            'entregue_por' => null,
            'protocolo' => null,
        ], 7);
    }

    public function testTransicaoProibidaEReaberturaPorColaboradorNaoGravamNada(): void
    {
        $entregue = $this->nova(['status' => 'ENTREGUE']);

        try {
            $this->servico->mudarStatus($this->admin(), $entregue, $this->mudanca('DISPENSADA', 'Motivo'), 1);
            self::fail('ENTREGUE → DISPENSADA é proibida.');
        } catch (ConflitoException $erro) {
            self::assertSame(ObrigacaoStatusService::MENSAGEM_PROIBIDA, $erro->getMessage());
        }

        try {
            $this->servico->mudarStatus($this->colaborador(), $entregue, $this->mudanca('EM_ANDAMENTO', 'Motivo'), 1);
            self::fail('Só ADMIN reabre.');
        } catch (AcessoNegadoException) {
        }

        self::assertSame('ENTREGUE', $this->registro($entregue)['status']);
        self::assertSame([], $this->logs($entregue));
    }

    public function testCamposInvalidosNaoChegamAoBanco(): void
    {
        $id = $this->nova();

        try {
            $this->servico->mudarStatus($this->colaborador(), $id, $this->mudanca('DISPENSADA'), 1);
            self::fail('A dispensa exige justificativa.');
        } catch (\InvalidArgumentException) {
        }

        self::assertSame('PENDENTE', $this->registro($id)['status']);
        self::assertSame([], $this->logs($id));
    }

    public function testObrigacaoDeOutroEscritorioResponde404SemGravarLog(): void
    {
        $id = $this->nova();
        $deFora = new UsuarioLogado($this->adminB, $this->escritorioB, 'ADMIN', null, 'Admin B', []);

        try {
            $this->servico->mudarStatus($deFora, $id, $this->mudanca('EM_ANDAMENTO'), 1);
            self::fail('Obrigação de outro escritório deve responder 404.');
        } catch (NaoEncontradoException) {
        }

        self::assertSame('PENDENTE', $this->registro($id)['status']);
        self::assertSame([], $this->logs($id));
        self::assertSame([], $this->obrigacoes->historico($this->escritorioB, $id));
    }

    public function testFalhaNaAuditoriaDesfazAMudancaDeStatus(): void
    {
        $id = $this->nova();
        // O UPDATE passa, mas o INSERT do log falha na FK do usuário: a transação inteira é desfeita.
        $fantasma = new UsuarioLogado(self::USUARIO_INEXISTENTE, $this->escritorioA, 'ADMIN', null, 'Fantasma', []);

        try {
            $this->servico->mudarStatus($fantasma, $id, $this->mudanca('EM_ANDAMENTO'), 1);
            self::fail('A auditoria deveria falhar.');
        } catch (PDOException) {
        }

        $obrigacao = $this->registro($id);
        self::assertSame('PENDENTE', $obrigacao['status']);
        self::assertSame(1, (int) $obrigacao['versao']);
        self::assertSame([], $this->logs($id));
    }

    public function testHistoricoTrazSoEventosDeStatusDaObrigacaoEmOrdemDecrescente(): void
    {
        $id = $this->nova();
        $outra = $this->nova();
        $auditoria = new AuditoriaService(new LogAtividade());
        // Eventos que o histórico NÃO deve mostrar: criar/editar (D2), outra obrigação e outro escritório.
        $auditoria->registrar($this->admin(), 'obrigacao', $id, 'criar');
        $auditoria->registrar($this->admin(), 'obrigacao', $id, 'editar');
        $auditoria->registrar($this->admin(), 'empresa', $id, 'status:ENTREGUE', ['de' => 'X', 'para' => 'Y']);
        $deFora = new UsuarioLogado($this->adminB, $this->escritorioB, 'ADMIN', null, 'Admin B', []);
        $auditoria->registrar($deFora, 'obrigacao', $id, 'status:DISPENSADA', ['de' => 'X', 'para' => 'Y']);
        $this->servico->mudarStatus($this->colaborador(), $outra, $this->mudanca('EM_ANDAMENTO'), 1);

        $this->servico->mudarStatus($this->colaborador(), $id, $this->mudanca('EM_ANDAMENTO', 'Começando'), 1);
        $this->servico->mudarStatus($this->colaborador(), $id, $this->mudanca('ENTREGUE', null, 'REC-9'), 2);
        $this->servico->mudarStatus($this->admin(), $id, $this->mudanca('EM_ANDAMENTO', 'Retificar'), 3);

        $historico = $this->obrigacoes->historico($this->escritorioA, $id);

        // Os três eventos caem no mesmo segundo: o desempate é pelo id (mais recente primeiro).
        self::assertSame(
            [
                ['ENTREGUE', 'EM_ANDAMENTO', 'Retificar', null],
                ['EM_ANDAMENTO', 'ENTREGUE', null, 'REC-9'],
                ['PENDENTE', 'EM_ANDAMENTO', 'Começando', null],
            ],
            array_map(
                static fn (array $evento): array => [$evento['de'], $evento['para'], $evento['justificativa'], $evento['protocolo']],
                $historico
            )
        );
        self::assertSame('Usuário C01 de teste', $historico[0]['usuario_nome']);
        self::assertMatchesRegularExpression('/\A\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\z/', $historico[0]['criado_em']);
        self::assertSame([], $this->obrigacoes->historico($this->escritorioA, $this->nova()));
    }

    /**
     * @param array<string, mixed> $opcoes
     */
    private function nova(array $opcoes = []): int
    {
        return $this->fiscal->criarObrigacao(
            $this->escritorioA,
            $this->empresaA,
            $this->fiscal->criarTipo($this->escritorioA),
            $this->adminA,
            $opcoes
        );
    }

    /**
     * @return array{status: string, justificativa: string|null, protocolo: string|null}
     */
    private function mudanca(string $status, ?string $justificativa = null, ?string $protocolo = null): array
    {
        return ['status' => $status, 'justificativa' => $justificativa, 'protocolo' => $protocolo];
    }

    private function admin(): UsuarioLogado
    {
        return new UsuarioLogado($this->adminA, $this->escritorioA, 'ADMIN', null, 'Admin A', []);
    }

    private function colaborador(): UsuarioLogado
    {
        return new UsuarioLogado($this->colaboradorA, $this->escritorioA, 'COLABORADOR', null, 'Colaborador A', []);
    }

    /**
     * @return array<string, mixed>
     */
    private function registro(int $id): array
    {
        $stmt = $this->conexao()->prepare('SELECT * FROM obrigacoes WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $linha = $stmt->fetch();

        return is_array($linha) ? $linha : [];
    }

    /**
     * Logs de status da obrigação, do mais antigo para o mais recente.
     *
     * @return list<array<string, mixed>>
     */
    private function logs(int $id): array
    {
        $stmt = $this->conexao()->prepare(
            "SELECT * FROM logs_atividade
             WHERE escritorio_id = :escritorio AND entidade = 'obrigacao' AND entidade_id = :id
               AND acao LIKE 'status:%'
             ORDER BY id ASC"
        );
        $stmt->execute(['escritorio' => $this->escritorioA, 'id' => $id]);

        return array_values($stmt->fetchAll());
    }

    /**
     * @param array<string, mixed> $log
     * @return array<string, mixed>
     */
    private function detalhes(array $log): array
    {
        $detalhes = json_decode((string) $log['detalhes'], true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($detalhes);
        ksort($detalhes);
        $esperadas = ['de', 'justificativa', 'para', 'protocolo'];
        self::assertSame($esperadas, array_keys($detalhes));

        return ['de' => $detalhes['de'], 'para' => $detalhes['para'], 'justificativa' => $detalhes['justificativa'], 'protocolo' => $detalhes['protocolo']];
    }

    private function novoEscritorio(): int
    {
        $id = $this->cadastro->criarEscritorio();
        $this->escritoriosCriados[] = $id;

        return $id;
    }

    private function limparEscritorio(int $escritorioId): void
    {
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
