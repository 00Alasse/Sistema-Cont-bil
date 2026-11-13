<?php

declare(strict_types=1);

namespace Tests\Integration\Gestao;

use App\Services\Gestao\IndiceRegularidadeService;
use App\Support\Cnpj;
use App\Support\RelogioFixo;
use DateTimeImmutable;
use Tests\Integration\IntegrationTestCase;
use Tests\Support\FabricaCadastro;
use Tests\Support\FabricaFiscal;

/**
 * Consulta agregada do Índice de Regularidade. "Hoje" é 10/03/2026 (RelogioFixo):
 * janela de 10/03/2025 a 09/03/2026; atraso crítico = vencimento antes de 08/02/2026.
 */
final class IndiceRegularidadeServiceTest extends IntegrationTestCase
{
    private const HOJE = '2026-03-10 14:30:00';

    private IndiceRegularidadeService $servico;

    private FabricaCadastro $cadastro;

    private FabricaFiscal $fiscal;

    private int $escritorioA;

    private int $escritorioB;

    private int $usuarioA;

    private int $usuarioB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->servico = $this->servicoEm(self::HOJE);
        $this->cadastro = new FabricaCadastro($this->conexao());
        $this->fiscal = new FabricaFiscal($this->conexao());
        $this->escritorioA = $this->cadastro->criarEscritorio();
        $this->escritorioB = $this->cadastro->criarEscritorio();
        $this->usuarioA = $this->cadastro->criarUsuario($this->escritorioA);
        $this->usuarioB = $this->cadastro->criarUsuario($this->escritorioB);
    }

    public function testLimitesDaJanelaDe12Meses(): void
    {
        $empresa = $this->empresa();
        $this->obrigacao($empresa, '2025-03-09', 'ENTREGUE'); // um dia antes do início: fora
        $this->obrigacao($empresa, '2025-03-10', 'ENTREGUE'); // primeiro dia: dentro
        $this->obrigacao($empresa, '2026-03-09', 'ENTREGUE'); // ontem, último dia: dentro
        $this->obrigacao($empresa, '2026-03-10', 'ENTREGUE'); // hoje: fora

        $contadores = $this->indice($empresa)['contadores'];

        self::assertSame(2, $contadores['total']);
        self::assertSame(2, $contadores['no_prazo']);
        self::assertSame(100, $contadores['pontualidade']);
    }

    public function testDispensadaFicaForaDoTotalEDasAtrasadas(): void
    {
        $empresa = $this->empresa();
        $this->obrigacao($empresa, '2025-10-20', 'ENTREGUE');
        $this->obrigacao($empresa, '2025-11-20', 'DISPENSADA');
        $this->obrigacao($empresa, '2024-11-20', 'DISPENSADA');

        $resultado = $this->indice($empresa);

        self::assertSame(1, $resultado['contadores']['total']);
        self::assertSame(0, $resultado['contadores']['atrasadas']);
        self::assertSame(100, $resultado['score']);
        self::assertSame('verde', $resultado['semaforo']);
    }

    public function testEntregaNoPrazoComparaADataSemAHora(): void
    {
        $empresa = $this->empresa();
        $this->obrigacao($empresa, '2026-01-20', 'ENTREGUE', ['entregue_em' => '2026-01-20 23:59:59']);
        $this->obrigacao($empresa, '2026-02-20', 'ENTREGUE', ['entregue_em' => '2026-02-21 00:00:00']);

        $resultado = $this->indice($empresa);

        self::assertSame(2, $resultado['contadores']['total']);
        self::assertSame(1, $resultado['contadores']['no_prazo']);
        self::assertSame(50, $resultado['score']);
        self::assertSame('vermelho', $resultado['semaforo']);
        self::assertSame(['Pontualidade de 50% em 12 meses'], $resultado['motivos']);
    }

    public function testAbertaVencidaNaJanelaContaNoTotalENasAtrasadas(): void
    {
        $empresa = $this->empresa();
        for ($mes = 4; $mes <= 12; $mes++) {
            $this->obrigacao($empresa, sprintf('2025-%02d-15', $mes), 'ENTREGUE');
        }
        $this->obrigacao($empresa, '2026-03-01', 'EM_ANDAMENTO');

        $resultado = $this->indice($empresa);

        // 9 de 10 no prazo = 90%; − 10 pela atrasada = 80.
        self::assertSame(10, $resultado['contadores']['total']);
        self::assertSame(9, $resultado['contadores']['no_prazo']);
        self::assertSame(1, $resultado['contadores']['atrasadas']);
        self::assertSame(0, $resultado['contadores']['atrasadas_criticas']);
        self::assertSame(80, $resultado['score']);
        self::assertSame('amarelo', $resultado['semaforo']);
        self::assertSame(['1 obrigação atrasada', 'Pontualidade de 90% em 12 meses'], $resultado['motivos']);
    }

    public function testLimiteDoAtrasoCriticoEm30Dias(): void
    {
        $ha30Dias = $this->empresa();
        $this->obrigacao($ha30Dias, '2026-02-08', 'PENDENTE');
        $ha31Dias = $this->empresa();
        $this->obrigacao($ha31Dias, '2026-02-07', 'AGUARDANDO_CLIENTE');

        $carteira = $this->servico->calcularCarteira($this->escritorioA);

        self::assertSame(0, $carteira[$ha30Dias]['contadores']['atrasadas_criticas']);
        self::assertSame(1, $carteira[$ha31Dias]['contadores']['atrasadas_criticas']);
        self::assertSame('vermelho', $carteira[$ha31Dias]['semaforo']);
        self::assertContains('Obrigação atrasada há mais de 30 dias', $carteira[$ha31Dias]['motivos']);
    }

    public function testAtrasadaAntesDaJanelaForcaVermelhoSemHistorico(): void
    {
        $empresa = $this->empresa();
        $this->obrigacao($empresa, '2024-12-10', 'PENDENTE');

        $resultado = $this->indice($empresa);

        self::assertSame(0, $resultado['contadores']['total']);
        self::assertSame(1, $resultado['contadores']['atrasadas']);
        self::assertSame(1, $resultado['contadores']['atrasadas_criticas']);
        self::assertNull($resultado['score']);
        self::assertSame('vermelho', $resultado['semaforo']);
    }

    public function testEmpresaSemObrigacoesVencidasNaoTemHistorico(): void
    {
        $semNada = $this->empresa();
        $soFuturas = $this->empresa();
        $this->obrigacao($soFuturas, '2026-03-20', 'PENDENTE');

        $carteira = $this->servico->calcularCarteira($this->escritorioA);

        foreach ([$semNada, $soFuturas] as $empresa) {
            self::assertNull($carteira[$empresa]['score']);
            self::assertSame('sem_historico', $carteira[$empresa]['semaforo']);
            self::assertSame(['Sem obrigações vencidas nos últimos 12 meses'], $carteira[$empresa]['motivos']);
        }
    }

    public function testDocumentosVencidosValemZeroSemADo01(): void
    {
        $empresa = $this->empresa();
        $this->obrigacao($empresa, '2026-01-20', 'ENTREGUE');

        self::assertSame(0, $this->indice($empresa)['contadores']['docs_vencidos']);
    }

    public function testCarteiraPadraoTrazSoAsAtivasEListaExplicitaAceitaInativa(): void
    {
        $ativa = $this->empresa();
        $inativa = $this->empresa('INATIVA');

        $carteira = $this->servico->calcularCarteira($this->escritorioA);
        self::assertSame([$ativa], array_keys($carteira));

        $explicita = $this->servico->calcularCarteira($this->escritorioA, [$inativa, $ativa, $inativa]);
        self::assertEqualsCanonicalizing([$ativa, $inativa], array_keys($explicita));
        self::assertNotNull($this->servico->calcular($this->escritorioA, $inativa));
    }

    public function testNuncaMisturaEscritorios(): void
    {
        $empresaA = $this->empresa();
        $this->obrigacao($empresaA, '2026-01-20', 'ENTREGUE');
        $empresaB = $this->cadastro->criarEmpresa(
            $this->escritorioB,
            Cnpj::gerarFicticio(random_int(1, 99_999_999)),
            'Empresa B'
        );
        $this->fiscal->criarObrigacao(
            $this->escritorioB,
            $empresaB,
            $this->fiscal->criarTipo($this->escritorioB),
            $this->usuarioB,
            ['competencia' => '2026-01-01', 'vencimento' => '2026-01-20']
        );

        self::assertSame([$empresaA], array_keys($this->servico->calcularCarteira($this->escritorioA)));
        self::assertSame([], $this->servico->calcularCarteira($this->escritorioA, [$empresaB]));
        self::assertNull($this->servico->calcular($this->escritorioA, $empresaB));
        self::assertSame([$empresaA], array_keys($this->servico->calcularCarteira($this->escritorioA, [$empresaA, $empresaB])));
        // A obrigação aberta de B não vaza para o índice de A.
        self::assertSame(0, $this->indice($empresaA)['contadores']['atrasadas']);
    }

    public function testListaVaziaOuInvalidaNaoConsultaNada(): void
    {
        $this->empresa();

        self::assertSame([], $this->servico->calcularCarteira($this->escritorioA, []));
        self::assertSame([], $this->servico->calcularCarteira($this->escritorioA, [0, -3]));
    }

    public function testQuantidadeDeEmpresasNaoAumentaONumeroDeConsultas(): void
    {
        $empresas = [];
        for ($i = 0; $i < 6; $i++) {
            $empresas[] = $empresa = $this->empresa();
            $this->obrigacao($empresa, '2026-01-20', 'ENTREGUE');
            $this->obrigacao($empresa, '2026-02-01', 'PENDENTE');
        }

        $umaEmpresa = $this->consultasDuranteACarteira([$empresas[0]]);
        $seisEmpresas = $this->consultasDuranteACarteira($empresas);
        $carteiraToda = $this->consultasDuranteACarteira(null);

        self::assertSame($umaEmpresa, $seisEmpresas);
        self::assertSame($umaEmpresa, $carteiraToda);
        // Uma consulta agregada (execução do prepared statement).
        self::assertLessThanOrEqual(1, $umaEmpresa);
    }

    public function testTrocarORelogioMudaOResultado(): void
    {
        $empresa = $this->empresa();
        $this->obrigacao($empresa, '2026-03-20', 'PENDENTE');

        self::assertSame('sem_historico', $this->indice($empresa)['semaforo']);

        // Em 25/04/2026 a obrigação está vencida há 36 dias.
        $depois = $this->servicoEm('2026-04-25 09:00:00')->calcular($this->escritorioA, $empresa);
        self::assertNotNull($depois);
        self::assertSame(1, $depois['contadores']['total']);
        self::assertSame(1, $depois['contadores']['atrasadas_criticas']);
        self::assertSame(0, $depois['score']);
        self::assertSame('vermelho', $depois['semaforo']);
    }

    private function servicoEm(string $agora): IndiceRegularidadeService
    {
        return new IndiceRegularidadeService(new RelogioFixo(new DateTimeImmutable($agora)));
    }

    private function empresa(string $status = 'ATIVA'): int
    {
        return $this->cadastro->criarEmpresa(
            $this->escritorioA,
            Cnpj::gerarFicticio(random_int(1, 99_999_999)),
            'Empresa ' . bin2hex(random_bytes(3)),
            ['status' => $status]
        );
    }

    /**
     * Cada obrigação usa um tipo novo, para não esbarrar na chave única (empresa, tipo, competência).
     *
     * @param array<string, mixed> $opcoes
     */
    private function obrigacao(int $empresa, string $vencimento, string $status, array $opcoes = []): int
    {
        return $this->fiscal->criarObrigacao(
            $this->escritorioA,
            $empresa,
            $this->fiscal->criarTipo($this->escritorioA),
            $this->usuarioA,
            ['competencia' => substr($vencimento, 0, 8) . '01', 'vencimento' => $vencimento, 'status' => $status] + $opcoes
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function indice(int $empresa): array
    {
        $resultado = $this->servico->calcular($this->escritorioA, $empresa);
        self::assertNotNull($resultado);

        return $resultado;
    }

    /**
     * Quantos comandos o servidor executou durante o cálculo, descontando a própria medição
     * (contador "Questions" da sessão, disponível no MySQL e no MariaDB).
     *
     * @param list<int>|null $empresas
     */
    private function consultasDuranteACarteira(?array $empresas): int
    {
        $antesDaMedicao = $this->questoes();
        $custoDaMedicao = $this->questoes() - $antesDaMedicao;

        $antes = $this->questoes();
        $this->servico->calcularCarteira($this->escritorioA, $empresas);
        $depois = $this->questoes();

        return $depois - $antes - $custoDaMedicao;
    }

    private function questoes(): int
    {
        $linha = $this->conexao()->query("SHOW SESSION STATUS LIKE 'Questions'")->fetch();

        return is_array($linha) ? (int) ($linha['Value'] ?? $linha['value'] ?? 0) : 0;
    }
}
