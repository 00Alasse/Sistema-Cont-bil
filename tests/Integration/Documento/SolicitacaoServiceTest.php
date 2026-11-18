<?php

declare(strict_types=1);

namespace Tests\Integration\Documento;

use App\Core\Paginacao;
use App\Core\Request;
use App\Core\UsuarioLogado;
use App\Exceptions\NaoEncontradoException;
use App\Models\Documento\SolicitacaoDocumento;
use App\Models\Nucleo\LogAtividade;
use App\Services\Documento\SolicitacaoService;
use App\Services\Nucleo\AuditoriaService;
use App\Support\Cnpj;
use PDOException;
use Tests\Integration\IntegrationTestCase;
use Tests\Support\FabricaCadastro;

/**
 * Histórico das solicitações (Melhoria 2): cada operação grava a mudança e o evento em
 * logs_atividade na mesma transação.
 *
 * Esta classe NÃO usa a transação externa do IntegrationTestCase: dentro de uma transação aberta,
 * Database::transacao apenas executa o callback, e assim não daria para provar o rollback.
 * Os dados criados são apagados no tearDown (mesmo recurso de ObrigacaoStatusIntegracaoTest).
 */
final class SolicitacaoServiceTest extends IntegrationTestCase
{
    private const USUARIO_INEXISTENTE = 2_000_000_000;

    private SolicitacaoService $servico;

    private SolicitacaoDocumento $solicitacoes;

    private FabricaCadastro $cadastro;

    /** @var list<int> */
    private array $escritoriosCriados = [];

    private int $escritorioA;

    private int $escritorioB;

    private int $adminA;

    private int $clienteA;

    private int $adminB;

    private int $empresaA;

    private int $empresaB;

    protected function iniciarTransacaoExterna(): bool
    {
        return false;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->solicitacoes = new SolicitacaoDocumento();
        $this->servico = new SolicitacaoService($this->solicitacoes, new AuditoriaService(new LogAtividade()));
        $this->cadastro = new FabricaCadastro($this->conexao());

        $this->escritorioA = $this->novoEscritorio();
        $this->escritorioB = $this->novoEscritorio();
        $this->adminA = $this->cadastro->criarUsuario($this->escritorioA, 'ADMIN');
        $this->adminB = $this->cadastro->criarUsuario($this->escritorioB, 'ADMIN');
        $this->empresaA = $this->cadastro->criarEmpresa($this->escritorioA, Cnpj::gerarFicticio(random_int(1, 99_999_999)), 'Empresa Docs A');
        $this->empresaB = $this->cadastro->criarEmpresa($this->escritorioB, Cnpj::gerarFicticio(random_int(1, 99_999_999)), 'Empresa Docs B');
        $this->clienteA = $this->cadastro->criarUsuario($this->escritorioA, 'CLIENTE', $this->empresaA);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        foreach ($this->escritoriosCriados as $escritorioId) {
            $this->limparEscritorio($escritorioId);
        }
    }

    public function testCriarGravaOEventoComTituloPrazoEUsuario(): void
    {
        $id = $this->criar('Extrato de março');

        $logs = $this->logs($id);
        self::assertCount(1, $logs);
        self::assertSame('criar', $logs[0]['acao']);
        self::assertSame($this->adminA, (int) $logs[0]['usuario_id']);
        self::assertSame($this->escritorioA, (int) $logs[0]['escritorio_id']);
        self::assertSame(['prazo' => '2026-12-10', 'titulo' => 'Extrato de março'], $this->detalhes($logs[0]));
        self::assertSame($this->adminA, (int) $this->registro($id)['criado_por']);
    }

    public function testEnvioInicialGravaEventoComONomeOriginalSemOCaminhoDoArquivo(): void
    {
        $id = $this->criar('Notas fiscais');

        $this->servico->anexarArquivo($this->cliente(), $id, 'a1b2c3d4.pdf', 'notas.pdf', '2026-11-17 10:00:00');

        $logs = $this->logs($id);
        self::assertSame(['criar', 'enviar'], array_column($logs, 'acao'));
        self::assertSame($this->clienteA, (int) $logs[1]['usuario_id']);
        self::assertSame(['arquivo' => 'notas.pdf'], $this->detalhes($logs[1]));
        self::assertStringNotContainsString('a1b2c3d4', (string) $logs[1]['detalhes']);
        self::assertSame('enviada', $this->registro($id)['status']);
    }

    public function testReenvioDepoisDaRecusaPreservaOHistoricoAnterior(): void
    {
        $id = $this->criar('Contrato social');
        $this->servico->anexarArquivo($this->cliente(), $id, 'primeiro.pdf', 'contrato-v1.pdf', '2026-11-17 10:00:00');
        $this->servico->recusar($this->admin(), $id, 'Falta a página 2');

        $this->servico->anexarArquivo($this->cliente(), $id, 'segundo.pdf', 'contrato-v2.pdf', '2026-11-17 11:00:00');

        // O registro guarda só o estado atual (o motivo é limpo no reenvio)...
        $atual = $this->registro($id);
        self::assertSame('enviada', $atual['status']);
        self::assertNull($atual['motivo_recusa']);
        // ...mas o histórico continua com o envio anterior, a recusa e o motivo.
        $historico = $this->solicitacoes->historico($this->escritorioA, [$id])[$id];
        self::assertSame(['criar', 'enviar', 'recusar', 'reenviar'], array_column($historico, 'acao'));
        self::assertSame('contrato-v1.pdf', $historico[1]['arquivo']);
        self::assertSame('Falta a página 2', $historico[2]['motivo']);
        self::assertSame('contrato-v2.pdf', $historico[3]['arquivo']);
    }

    public function testRecusaGravaOMotivoEAceiteGravaOEvento(): void
    {
        $id = $this->criar('Extrato bancário');
        $this->servico->anexarArquivo($this->cliente(), $id, 'x.pdf', 'extrato.pdf', '2026-11-17 10:00:00');

        $this->servico->recusar($this->admin(), $id, 'Ilegível');
        $recusa = $this->logs($id)[2];
        self::assertSame('recusar', $recusa['acao']);
        self::assertSame(['motivo' => 'Ilegível'], $this->detalhes($recusa));
        self::assertSame($this->adminA, (int) $recusa['usuario_id']);

        $this->servico->aceitar($this->admin(), $id);
        $logs = $this->logs($id);
        self::assertSame('aceitar', $logs[3]['acao']);
        self::assertSame([], $this->detalhes($logs[3]));
        self::assertSame('aceita', $this->registro($id)['status']);
    }

    public function testFalhaAoGravarOEventoDesfazAOperacaoPrincipal(): void
    {
        $id = $this->criar('Folha de pagamento');
        // O UPDATE passa, mas o INSERT do log falha na FK do usuário: a transação inteira é desfeita.
        $fantasma = new UsuarioLogado(self::USUARIO_INEXISTENTE, $this->escritorioA, 'ADMIN', null, 'Fantasma', []);

        try {
            $this->servico->recusar($fantasma, $id, 'Não deveria ficar gravado');
            self::fail('O registro do evento deveria falhar.');
        } catch (PDOException) {
        }

        $registro = $this->registro($id);
        self::assertSame('aberta', $registro['status']);
        self::assertNull($registro['motivo_recusa']);
        self::assertSame(['criar'], array_column($this->logs($id), 'acao'));
    }

    public function testFalhaNaOperacaoPrincipalNaoDeixaEventoOrfao(): void
    {
        // Empresa de outro escritório: a FK composta (empresa_id, escritorio_id) rejeita o INSERT.
        try {
            $this->servico->criar($this->admin(), ['empresa_id' => $this->empresaB, 'titulo' => 'Inválida', 'prazo' => '2026-12-10']);
            self::fail('A criação deveria falhar.');
        } catch (PDOException) {
        }

        // Solicitação de outro escritório: 404 antes de qualquer escrita.
        $deB = $this->solicitacoes->criar($this->escritorioB, ['empresa_id' => $this->empresaB, 'titulo' => 'De B', 'prazo' => '2026-12-10', 'criado_por' => $this->adminB]);
        try {
            $this->servico->aceitar($this->admin(), $deB);
            self::fail('Deveria responder 404.');
        } catch (NaoEncontradoException) {
        }

        self::assertSame(0, $this->contarLogsDoEscritorio($this->escritorioA));
        self::assertSame(0, $this->contarLogsDoEscritorio($this->escritorioB));
        self::assertSame('aberta', $this->registro($deB)['status']);
    }

    public function testHistoricoRespeitaOEscritorioEASolicitacao(): void
    {
        $id = $this->criar('Balancete');
        $outra = $this->criar('Outra solicitação');
        $this->servico->recusar($this->admin(), $outra, 'Motivo da outra');
        // Evento de outra entidade com o mesmo ID não pode aparecer no histórico.
        (new AuditoriaService(new LogAtividade()))->registrar($this->admin(), 'obrigacao', $id, 'editar');

        $historico = $this->solicitacoes->historico($this->escritorioA, [$id]);
        self::assertSame([$id], array_keys($historico));
        self::assertSame(['criar'], array_column($historico[$id], 'acao'));
        self::assertSame('Usuário C01 de teste', $historico[$id][0]['usuario_nome']);

        // Outro escritório não enxerga os eventos, mesmo pedindo os IDs.
        self::assertSame([], $this->solicitacoes->historico($this->escritorioB, [$id, $outra]));
    }

    public function testSolicitacaoAntigaSemEventosNaoTemHistoricoInventado(): void
    {
        // Criada direto pelo model, como as solicitações anteriores à implantação do recurso.
        $antiga = $this->solicitacoes->criar($this->escritorioA, ['empresa_id' => $this->empresaA, 'titulo' => 'Antiga', 'prazo' => '2026-12-10', 'criado_por' => $this->adminA]);

        self::assertSame([], $this->solicitacoes->historico($this->escritorioA, [$antiga]));
        self::assertSame([], $this->logs($antiga));
    }

    public function testFiltroVencidaTrazAbertasERecusadasComPrazoVencidoComoOIndicador(): void
    {
        $ontem = date('Y-m-d', strtotime('-1 day'));
        $amanha = date('Y-m-d', strtotime('+1 day'));
        $abertaVencida = $this->criar('Aberta vencida', $ontem);
        $recusadaVencida = $this->criar('Recusada vencida', $ontem);
        $this->servico->anexarArquivo($this->cliente(), $recusadaVencida, 'r.pdf', 'r.pdf', '2026-11-17 10:00:00');
        $this->servico->recusar($this->admin(), $recusadaVencida, 'Refazer');
        $this->criar('Aberta no prazo', $amanha);
        $enviadaVencida = $this->criar('Enviada vencida', $ontem);
        $this->servico->anexarArquivo($this->cliente(), $enviadaVencida, 'e.pdf', 'e.pdf', '2026-11-17 10:00:00');
        $aceitaVencida = $this->criar('Aceita vencida', $ontem);
        $this->servico->anexarArquivo($this->cliente(), $aceitaVencida, 'a.pdf', 'a.pdf', '2026-11-17 10:00:00');
        $this->servico->aceitar($this->admin(), $aceitaVencida);

        $pagina = $this->solicitacoes->listar($this->escritorioA, ['situacao' => 'vencida'], $this->paginacao());
        $ids = array_map(static fn (array $item): int => (int) $item['id'], $pagina->itens);
        sort($ids);

        self::assertSame([$abertaVencida, $recusadaVencida], $ids);
        self::assertSame(2, $pagina->total);
        self::assertSame($this->solicitacoes->contarAtrasadas($this->escritorioA), $pagina->total);
        // Sem o filtro, as cinco aparecem; valor desconhecido não filtra.
        self::assertSame(5, $this->solicitacoes->listar($this->escritorioA, [], $this->paginacao())->total);
        self::assertSame(5, $this->solicitacoes->listar($this->escritorioA, ['situacao' => 'x'], $this->paginacao())->total);
    }

    private function criar(string $titulo, string $prazo = '2026-12-10'): int
    {
        return $this->servico->criar($this->admin(), ['empresa_id' => $this->empresaA, 'titulo' => $titulo, 'prazo' => $prazo]);
    }

    private function admin(): UsuarioLogado
    {
        return new UsuarioLogado($this->adminA, $this->escritorioA, 'ADMIN', null, 'Admin A', []);
    }

    private function cliente(): UsuarioLogado
    {
        return new UsuarioLogado($this->clienteA, $this->escritorioA, 'CLIENTE', $this->empresaA, 'Cliente A', []);
    }

    private function paginacao(): Paginacao
    {
        return Paginacao::deRequest(new Request('GET', '/solicitacoes', ['pagina' => 1]), 15);
    }

    /**
     * @return array<string, mixed>
     */
    private function registro(int $id): array
    {
        $stmt = $this->conexao()->prepare('SELECT * FROM solicitacoes_documentos WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $linha = $stmt->fetch();

        return is_array($linha) ? $linha : [];
    }

    /**
     * Eventos da solicitação, do mais antigo para o mais recente.
     *
     * @return list<array<string, mixed>>
     */
    private function logs(int $id): array
    {
        $stmt = $this->conexao()->prepare(
            "SELECT * FROM logs_atividade WHERE entidade = 'solicitacao' AND entidade_id = :id ORDER BY id ASC"
        );
        $stmt->execute(['id' => $id]);

        return array_values($stmt->fetchAll());
    }

    private function contarLogsDoEscritorio(int $escritorioId): int
    {
        $stmt = $this->conexao()->prepare("SELECT COUNT(*) FROM logs_atividade WHERE escritorio_id = :e AND entidade = 'solicitacao'");
        $stmt->execute(['e' => $escritorioId]);

        return (int) $stmt->fetchColumn();
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

        return $detalhes;
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
            'DELETE FROM solicitacoes_documentos WHERE escritorio_id = :e',
            "DELETE FROM usuarios WHERE escritorio_id = :e AND perfil = 'CLIENTE'",
            'DELETE FROM empresas WHERE escritorio_id = :e',
            'DELETE FROM usuarios WHERE escritorio_id = :e',
            'DELETE FROM escritorios WHERE id = :e',
        ] as $sql) {
            $this->conexao()->prepare($sql)->execute(['e' => $escritorioId]);
        }
    }
}
