<?php

declare(strict_types=1);

namespace Tests\Integration\Documento;

use App\Core\Config;
use App\Core\Database;
use App\Core\Paginacao;
use App\Core\Request;
use App\Core\UsuarioLogado;
use App\Exceptions\ConflitoException;
use App\Exceptions\NaoEncontradoException;
use App\Models\Documento\SolicitacaoDocumento;
use App\Models\Nucleo\LogAtividade;
use App\Services\Documento\SolicitacaoService;
use App\Services\Documento\UploadService;
use App\Services\Nucleo\AuditoriaService;
use App\Support\Cnpj;
use PDO;
use PDOException;
use RuntimeException;
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

    private const PDF = "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF\n";

    /** Pasta de uploads só deste teste (nunca a storage/uploads compartilhada). */
    private string $pastaUploads = '';

    private UploadService $uploads;

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
        $this->pastaUploads = sys_get_temp_dir() . '/tributo_m2_uploads_' . bin2hex(random_bytes(6));
        mkdir($this->pastaUploads, 0755, true);
        $this->uploads = new UploadService($this->pastaUploads);
    }

    protected function tearDown(): void
    {
        $arquivos = glob($this->pastaUploads . '/*');
        foreach ($arquivos === false ? [] : $arquivos as $arquivo) {
            unlink($arquivo);
        }
        if (is_dir($this->pastaUploads)) {
            rmdir($this->pastaUploads);
        }
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

        $this->servico->anexarArquivo($this->cliente(), $id, 'y.pdf', 'extrato-v2.pdf', '2026-11-17 11:00:00');
        $this->servico->aceitar($this->admin(), $id);
        $logs = $this->logs($id);
        self::assertSame(['criar', 'enviar', 'recusar', 'reenviar', 'aceitar'], array_column($logs, 'acao'));
        self::assertSame([], $this->detalhes($logs[4]));
        self::assertSame('aceita', $this->registro($id)['status']);
    }

    public function testFalhaAoGravarOEventoDesfazAOperacaoPrincipal(): void
    {
        $id = $this->criar('Folha de pagamento');
        $this->servico->anexarArquivo($this->cliente(), $id, 'f.pdf', 'folha.pdf', '2026-11-17 10:00:00');
        // O UPDATE passa, mas o INSERT do log falha na FK do usuário: a transação inteira é desfeita.
        $fantasma = new UsuarioLogado(self::USUARIO_INEXISTENTE, $this->escritorioA, 'ADMIN', null, 'Fantasma', []);

        try {
            $this->servico->recusar($fantasma, $id, 'Não deveria ficar gravado');
            self::fail('O registro do evento deveria falhar.');
        } catch (PDOException) {
        }

        $registro = $this->registro($id);
        self::assertSame('enviada', $registro['status']);
        self::assertNull($registro['motivo_recusa']);
        self::assertSame(['criar', 'enviar'], array_column($this->logs($id), 'acao'));
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
        $this->servico->anexarArquivo($this->cliente(), $outra, 'o.pdf', 'outra.pdf', '2026-11-17 10:00:00');
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

    // ---- Transições de estado (409 sem evento) ----

    public function testAceitarOuRecusarSolicitacaoAbertaResponde409SemEvento(): void
    {
        $id = $this->criar('Aberta');

        $this->esperarConflito(fn () => $this->servico->aceitar($this->admin(), $id));
        $this->esperarConflito(fn () => $this->servico->recusar($this->admin(), $id, 'Sem arquivo'));

        self::assertSame('aberta', $this->registro($id)['status']);
        self::assertNull($this->registro($id)['motivo_recusa']);
        self::assertSame(['criar'], array_column($this->logs($id), 'acao'));
    }

    public function testAceitarJaAceitaERecusarJaRecusadaOuAceitaRespondem409SemEvento(): void
    {
        $aceita = $this->criar('Aceita');
        $this->servico->anexarArquivo($this->cliente(), $aceita, 'a.pdf', 'a.pdf', '2026-11-17 10:00:00');
        $this->servico->aceitar($this->admin(), $aceita);
        $recusada = $this->criar('Recusada');
        $this->servico->anexarArquivo($this->cliente(), $recusada, 'r.pdf', 'r.pdf', '2026-11-17 10:00:00');
        $this->servico->recusar($this->admin(), $recusada, 'Primeiro motivo');

        $this->esperarConflito(fn () => $this->servico->aceitar($this->admin(), $aceita));
        $this->esperarConflito(fn () => $this->servico->recusar($this->admin(), $aceita, 'Tarde demais'));
        $this->esperarConflito(fn () => $this->servico->recusar($this->admin(), $recusada, 'Segundo motivo'));

        self::assertSame('aceita', $this->registro($aceita)['status']);
        self::assertSame(['criar', 'enviar', 'aceitar'], array_column($this->logs($aceita), 'acao'));
        self::assertSame('Primeiro motivo', $this->registro($recusada)['motivo_recusa']);
        self::assertSame(['criar', 'enviar', 'recusar'], array_column($this->logs($recusada), 'acao'));
    }

    public function testEnviarSolicitacaoAceitaResponde409SemEvento(): void
    {
        $id = $this->criar('Já aceita');
        $this->servico->anexarArquivo($this->cliente(), $id, 'aceito.pdf', 'aceito.pdf', '2026-11-17 10:00:00');
        $this->servico->aceitar($this->admin(), $id);

        $this->esperarConflito(fn () => $this->servico->anexarArquivo($this->cliente(), $id, 'novo.pdf', 'novo.pdf', '2026-11-17 12:00:00'));

        $registro = $this->registro($id);
        self::assertSame('aceita', $registro['status']);
        self::assertSame('aceito.pdf', $registro['arquivo_path']);
        self::assertSame(['criar', 'enviar', 'aceitar'], array_column($this->logs($id), 'acao'));
    }

    public function testEnviarAbertaEReenviarRecusadaContinuamFuncionando(): void
    {
        $id = $this->criar('Fluxo válido');

        $this->servico->anexarArquivo($this->cliente(), $id, 'v1.pdf', 'v1.pdf', '2026-11-17 10:00:00');
        self::assertSame('enviada', $this->registro($id)['status']);
        $this->servico->recusar($this->admin(), $id, 'Refazer');
        $this->servico->anexarArquivo($this->cliente(), $id, 'v2.pdf', 'v2.pdf', '2026-11-17 11:00:00');
        $reenviada = $this->registro($id);
        self::assertSame('enviada', $reenviada['status']);
        self::assertSame('v2.pdf', $reenviada['arquivo_path']);
        $this->servico->aceitar($this->admin(), $id);

        self::assertSame(['criar', 'enviar', 'recusar', 'reenviar', 'aceitar'], array_column($this->logs($id), 'acao'));
    }

    public function testDuasTentativasSobreOMesmoEstadoSoUmaTemSucessoEEvento(): void
    {
        // Duplo clique / duas abas: a segunda ação encontra o estado já mudado.
        $aceite = $this->criar('Duplo aceite');
        $this->servico->anexarArquivo($this->cliente(), $aceite, 'd.pdf', 'd.pdf', '2026-11-17 10:00:00');
        $this->servico->aceitar($this->admin(), $aceite);
        $this->esperarConflito(fn () => $this->servico->aceitar($this->admin(), $aceite));
        self::assertSame(1, array_count_values(array_column($this->logs($aceite), 'acao'))['aceitar']);

        $envio = $this->criar('Duplo envio');
        $this->servico->anexarArquivo($this->cliente(), $envio, 'e1.pdf', 'e1.pdf', '2026-11-17 10:00:00');
        $this->esperarConflito(fn () => $this->servico->anexarArquivo($this->cliente(), $envio, 'e2.pdf', 'e2.pdf', '2026-11-17 10:00:01'));
        self::assertSame(['criar', 'enviar'], array_column($this->logs($envio), 'acao'));
        self::assertSame('e1.pdf', $this->registro($envio)['arquivo_path']);
    }

    public function testUpdateConcorrenteEsperaATravaEDepoisNaoAlteraNada(): void
    {
        // Duas conexões reais: a 1ª aceita sem confirmar; a 2ª tenta o mesmo UPDATE e fica presa na trava
        // da linha (tempo de espera curto). Depois do commit da 1ª, o UPDATE da 2ª reavalia o WHERE e muda 0 linhas.
        $id = $this->criar('Concorrência');
        $this->servico->anexarArquivo($this->cliente(), $id, 'c.pdf', 'c.pdf', '2026-11-17 10:00:00');
        $primeira = Database::conexao();
        $segunda = $this->novaConexao();
        $segunda->exec('SET SESSION innodb_lock_wait_timeout = 1');
        $modeloPrimeira = new SolicitacaoDocumento(null, $primeira);
        $modeloSegunda = new SolicitacaoDocumento(null, $segunda);

        $primeira->beginTransaction();
        self::assertSame(1, $modeloPrimeira->aceitar($this->escritorioA, $id));
        try {
            $modeloSegunda->aceitar($this->escritorioA, $id);
            self::fail('O segundo UPDATE deveria esperar a trava da linha.');
        } catch (PDOException $erro) {
            self::assertSame(1205, (int) ($erro->errorInfo[1] ?? 0), 'lock wait timeout');
        }
        $primeira->commit();

        self::assertSame(0, $modeloSegunda->aceitar($this->escritorioA, $id));
        self::assertSame(0, $modeloSegunda->recusar($this->escritorioA, $id, 'Tarde demais'));
        self::assertSame('aceita', $this->registro($id)['status']);
    }

    // ---- Arquivo recém-enviado quando nada é confirmado ----

    public function testFalhaNoEventoDesfazOEnvioEApagaOArquivoNovo(): void
    {
        $id = $this->criar('Falha no evento');
        $novo = $this->gravarArquivo();
        $fantasma = new UsuarioLogado(self::USUARIO_INEXISTENTE, $this->escritorioA, 'CLIENTE', $this->empresaA, 'Fantasma', []);

        try {
            $this->enviarComLimpeza($fantasma, $id, $novo);
            self::fail('O registro do evento deveria falhar.');
        } catch (PDOException) {
        }

        self::assertFileDoesNotExist($this->pastaUploads . '/' . $novo['caminho_relativo']);
        self::assertSame('aberta', $this->registro($id)['status']);
        self::assertNull($this->registro($id)['arquivo_path']);
        self::assertSame(['criar'], array_column($this->logs($id), 'acao'));
    }

    public function testFalhaNaOperacaoPrincipalApagaOArquivoNovo(): void
    {
        $id = $this->criar('Já aceita');
        $this->servico->anexarArquivo($this->cliente(), $id, 'aceito.pdf', 'aceito.pdf', '2026-11-17 10:00:00');
        $this->servico->aceitar($this->admin(), $id);
        $novo = $this->gravarArquivo();

        $this->esperarConflito(fn () => $this->enviarComLimpeza($this->cliente(), $id, $novo));

        self::assertFileDoesNotExist($this->pastaUploads . '/' . $novo['caminho_relativo']);
        self::assertSame('aceito.pdf', $this->registro($id)['arquivo_path']);
    }

    public function testReenvioQueFalhaPreservaOArquivoAnterior(): void
    {
        $id = $this->criar('Reenvio com falha');
        $anterior = $this->gravarArquivo();
        $this->enviarComLimpeza($this->cliente(), $id, $anterior);
        $this->servico->recusar($this->admin(), $id, 'Refazer');
        $novo = $this->gravarArquivo();
        $fantasma = new UsuarioLogado(self::USUARIO_INEXISTENTE, $this->escritorioA, 'CLIENTE', $this->empresaA, 'Fantasma', []);

        try {
            $this->enviarComLimpeza($fantasma, $id, $novo);
            self::fail('O registro do evento deveria falhar.');
        } catch (PDOException) {
        }

        self::assertFileDoesNotExist($this->pastaUploads . '/' . $novo['caminho_relativo']);
        self::assertFileExists($this->pastaUploads . '/' . $anterior['caminho_relativo']);
        $registro = $this->registro($id);
        self::assertSame('recusada', $registro['status']);
        self::assertSame($anterior['caminho_relativo'], $registro['arquivo_path']);
    }

    public function testEnvioBemSucedidoMantemOArquivoReferenciado(): void
    {
        $id = $this->criar('Envio normal');
        $novo = $this->gravarArquivo();

        $this->enviarComLimpeza($this->cliente(), $id, $novo);

        self::assertFileExists($this->pastaUploads . '/' . $novo['caminho_relativo']);
        self::assertSame($novo['caminho_relativo'], $this->registro($id)['arquivo_path']);
        self::assertSame('enviada', $this->registro($id)['status']);
    }

    public function testFalhaAoApagarNaoSubstituiAExcecaoOriginalNemExpoeDetalhes(): void
    {
        $id = $this->criar('Já aceita');
        $this->servico->anexarArquivo($this->cliente(), $id, 'aceito.pdf', 'aceito.pdf', '2026-11-17 10:00:00');
        $this->servico->aceitar($this->admin(), $id);

        try {
            $this->servico->anexarArquivo($this->cliente(), $id, 'x.pdf', 'x.pdf', '2026-11-17 12:00:00', static function (): never {
                throw new RuntimeException('C:\\interno\\storage\\uploads\\segredo.pdf');
            });
            self::fail('Deveria responder 409.');
        } catch (ConflitoException $erro) {
            // A exceção que chega ao Kernel é a original (409), com mensagem para o cliente.
            self::assertSame('Esta solicitação não aceita novos arquivos no estado atual.', $erro->getMessage());
            self::assertNull($erro->getPrevious());
        }
    }

    public function testComTransacaoExternaOArquivoNaoEApagado(): void
    {
        // Dentro de uma transação externa não há certeza de rollback: o arquivo é mantido.
        $id = $this->criar('Transação externa');
        $this->servico->anexarArquivo($this->cliente(), $id, 'a.pdf', 'a.pdf', '2026-11-17 10:00:00');
        $this->servico->aceitar($this->admin(), $id);
        $novo = $this->gravarArquivo();

        Database::conexao()->beginTransaction();
        try {
            $this->esperarConflito(fn () => $this->enviarComLimpeza($this->cliente(), $id, $novo));
        } finally {
            Database::conexao()->rollBack();
        }

        self::assertFileExists($this->pastaUploads . '/' . $novo['caminho_relativo']);
    }

    private function esperarConflito(callable $acao): void
    {
        try {
            $acao();
            self::fail('Deveria responder 409 (ConflitoException).');
        } catch (ConflitoException) {
            self::addToAssertionCount(1);
        }
    }

    /**
     * Grava um PDF de verdade pelo UploadService, na pasta temporária deste teste.
     *
     * @return array{caminho_relativo: string, nome_original: string, mime: string}
     */
    private function gravarArquivo(): array
    {
        $origem = tempnam(sys_get_temp_dir(), 'tributo-m2-');
        self::assertIsString($origem);
        file_put_contents($origem, self::PDF);

        return $this->uploads->salvarUpload(['error' => UPLOAD_ERR_OK, 'size' => strlen(self::PDF), 'name' => 'documento.pdf', 'tmp_name' => $origem]);
    }

    /**
     * @param array{caminho_relativo: string, nome_original: string, mime: string} $arquivo
     */
    private function enviarComLimpeza(UsuarioLogado $usuario, int $id, array $arquivo): void
    {
        $this->servico->anexarArquivo(
            $usuario,
            $id,
            $arquivo['caminho_relativo'],
            $arquivo['nome_original'],
            '2026-11-17 10:00:00',
            fn (): bool => $this->uploads->removerEnvioNaoConfirmado($arquivo['caminho_relativo'])
        );
    }

    private function novaConexao(): PDO
    {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', (string) Config::obter('db.host'), (int) Config::obter('db.porta'), (string) Config::obter('db.banco'));

        return new PDO($dsn, (string) Config::obter('db.usuario'), (string) Config::obter('db.senha'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
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
