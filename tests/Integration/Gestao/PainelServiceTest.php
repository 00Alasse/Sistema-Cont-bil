<?php

declare(strict_types=1);

namespace Tests\Integration\Gestao;

use App\Core\CartaoPainel;
use App\Core\Modulos;
use App\Core\Paginacao;
use App\Core\Request;
use App\Core\Router;
use App\Core\UsuarioLogado;
use App\Models\Fiscal\Obrigacao;
use App\Services\Gestao\IndiceRegularidadeService;
use App\Services\Gestao\PainelService;
use App\Support\Cnpj;
use App\Support\RelogioFixo;
use DateTimeImmutable;
use InvalidArgumentException;
use Tests\Integration\IntegrationTestCase;
use Tests\Support\FabricaCadastro;
use Tests\Support\FabricaFiscal;

/**
 * Indicadores do painel do escritório (GE02). "Hoje" é 10/03/2026 (RelogioFixo):
 * mês atual = março/2026; janela do índice = 10/03/2025 a 09/03/2026.
 */
final class PainelServiceTest extends IntegrationTestCase
{
    private const AGORA = '2026-03-10 14:30:00';

    private const NOME_USUARIO = 'Usuário C01 de teste';

    private PainelService $painel;

    private FabricaCadastro $cadastro;

    private FabricaFiscal $fiscal;

    private int $escritorioA;

    private int $escritorioB;

    private int $admin;

    private int $colaborador;

    protected function setUp(): void
    {
        parent::setUp();
        $this->painel = new PainelService(new RelogioFixo(new DateTimeImmutable(self::AGORA)));
        $this->cadastro = new FabricaCadastro($this->conexao());
        $this->fiscal = new FabricaFiscal($this->conexao());
        $this->escritorioA = $this->cadastro->criarEscritorio();
        $this->escritorioB = $this->cadastro->criarEscritorio();
        $this->admin = $this->cadastro->criarUsuario($this->escritorioA, 'ADMIN');
        $this->colaborador = $this->cadastro->criarUsuario($this->escritorioA, 'COLABORADOR');
    }

    public function testEscopoPadraoPorPerfilEValorInvalidoVoltaAoPadrao(): void
    {
        $admin = $this->usuario($this->admin, 'ADMIN');
        $colaborador = $this->usuario($this->colaborador, 'COLABORADOR');

        self::assertSame('todos', PainelService::escopoEfetivo($admin, null));
        self::assertSame('meus', PainelService::escopoEfetivo($colaborador, null));
        self::assertSame('meus', PainelService::escopoEfetivo($admin, 'meus'));
        self::assertSame('todos', PainelService::escopoEfetivo($colaborador, 'todos'));
        foreach (['', 'TODOS', 'outro', ['meus'], 1] as $invalido) {
            self::assertSame('todos', PainelService::escopoEfetivo($admin, $invalido));
            self::assertSame('meus', PainelService::escopoEfetivo($colaborador, $invalido));
        }

        $this->expectException(InvalidArgumentException::class);
        $this->painel->montar($admin, 'qualquer');
    }

    public function testIndicadoresDeObrigacoesNosDoisEscopos(): void
    {
        $empresa = $this->empresa('Empresa Indicadores');
        $c = $this->colaborador;
        $this->obrigacao($empresa, '2026-03-10', 'PENDENTE', $c);                                  // vence hoje
        $this->obrigacao($empresa, '2026-03-17', 'EM_ANDAMENTO', $this->admin);                    // vence em 7 dias
        $this->obrigacao($empresa, '2026-03-09', 'PENDENTE', $c);                                  // atrasada
        $this->obrigacao($empresa, '2026-01-15', 'AGUARDANDO_CLIENTE', null);                      // atrasada
        $this->obrigacao($empresa, '2026-03-05', 'ENTREGUE', $c, '2026-03-05 10:00:00');          // março, no prazo
        $this->obrigacao($empresa, '2026-03-02', 'ENTREGUE', $this->admin, '2026-03-03 08:00:00'); // março, com atraso
        $this->obrigacao($empresa, '2026-02-20', 'ENTREGUE', $c, '2026-02-20 08:00:00');          // fevereiro
        // Vence em fevereiro, mas foi entregue em março: entra em "entregues no mês" (P1).
        $this->obrigacao($empresa, '2026-02-27', 'ENTREGUE', $c, '2026-03-01 09:00:00');
        $this->obrigacaoDeOutroEscritorio('2026-03-10', 'PENDENTE');

        $todos = $this->painel->montar($this->usuario($this->admin, 'ADMIN'), 'todos');
        $meus = $this->painel->montar($this->usuario($c, 'COLABORADOR'), 'meus');

        self::assertSame('todos', $todos['escopo']);
        self::assertSame(
            ['vence_hoje' => 1, 'vence_7d' => 2, 'atrasadas' => 2, 'entregues_mes' => 3, 'entregues_no_prazo' => 1],
            $this->valores($todos)
        );
        self::assertSame(33, $todos['pontualidade']); // 1 de 3 = 33,3%

        self::assertSame('meus', $meus['escopo']);
        self::assertSame(
            ['vence_hoje' => 1, 'vence_7d' => 1, 'atrasadas' => 1, 'entregues_mes' => 2, 'entregues_no_prazo' => 1],
            $this->valores($meus)
        );
        self::assertSame(50, $meus['pontualidade']);

        // Links: filtros reais da listagem; "meus" preserva responsavel=meus.
        $marco = ['entregue_de' => '2026-03-01', 'entregue_ate' => '2026-03-31'];
        self::assertSame([
            'vence_hoje' => ['situacao' => 'vence_hoje'],
            'vence_7d' => ['situacao' => 'vence_7d'],
            'atrasadas' => ['situacao' => 'atrasada'],
            'entregues_mes' => $marco,
            'entregues_no_prazo' => $marco + ['situacao' => 'entregue_no_prazo'],
        ], array_map(static fn (array $i): array => $i['query'], $todos['obrigacoes']));
        foreach ($meus['obrigacoes'] as $indicador) {
            self::assertSame('/obrigacoes', $indicador['caminho']);
            self::assertSame('meus', $indicador['query']['responsavel']);
        }

        // Critério de aceite: cada número é igual ao total da listagem aberta pelo link.
        $this->assertNumerosIguaisAListagem($todos, $this->admin);
        $this->assertNumerosIguaisAListagem($meus, $c);
    }

    public function testSemEntregasNoMesAPontualidadeFicaVazia(): void
    {
        $empresa = $this->empresa('Empresa Sem Entregas');
        $this->obrigacao($empresa, '2026-02-20', 'ENTREGUE', null, '2026-02-20 08:00:00');

        $painel = $this->painel->montar($this->usuario($this->admin, 'ADMIN'), 'todos');

        self::assertSame(0, $painel['obrigacoes']['entregues_mes']['valor']);
        self::assertNull($painel['pontualidade']);
    }

    public function testSemaforoEPioresIndicesSeguemOIndiceDeRegularidade(): void
    {
        $verde = $this->empresa('Verde Ltda');
        $this->obrigacao($verde, '2025-12-10', 'ENTREGUE', null, '2025-12-10 09:00:00');
        $amarela = $this->empresa('Amarela Ltda');
        foreach (['2025-10-10', '2025-11-10', '2025-12-10'] as $vencimento) {
            $this->obrigacao($amarela, $vencimento, 'ENTREGUE', null, $vencimento . ' 09:00:00');
        }
        $this->obrigacao($amarela, '2025-09-10', 'ENTREGUE', null, '2025-09-11 09:00:00'); // 75: amarelo
        $bravo = $this->empresa('Bravo Ltda', $this->colaborador);
        $this->obrigacao($bravo, '2025-12-10', 'ENTREGUE', null, '2025-12-11 09:00:00');   // 0: vermelho
        $alfa1 = $this->empresa('Alfa Ltda');
        $this->obrigacao($alfa1, '2025-12-10', 'ENTREGUE', null, '2025-12-11 09:00:00');   // 0
        $alfa2 = $this->empresa('Alfa Ltda');
        $this->obrigacao($alfa2, '2025-12-10', 'ENTREGUE', null, '2025-12-11 09:00:00');   // 0, ID maior
        $zulu = $this->empresa('Zulu Ltda');
        $this->obrigacao($zulu, '2024-12-10', 'PENDENTE', null);                          // sem score, atraso crítico
        $meio = $this->empresa('Meio Ltda');
        $this->obrigacao($meio, '2025-11-10', 'ENTREGUE', null, '2025-11-10 09:00:00');
        $this->obrigacao($meio, '2025-12-10', 'ENTREGUE', null, '2025-12-11 09:00:00');   // 50
        $nova = $this->empresa('Nova Ltda', $this->colaborador);                           // sem histórico
        $inativa = $this->empresa('Inativa Ltda', null, 'INATIVA');
        $this->obrigacao($inativa, '2024-12-10', 'PENDENTE', null);
        $this->obrigacaoDeOutroEscritorio('2024-12-10', 'PENDENTE');

        $painel = $this->painel->montar($this->usuario($this->admin, 'ADMIN'), 'todos');

        self::assertSame(['verde' => 1, 'amarelo' => 1, 'vermelho' => 5, 'sem_historico' => 1], $painel['semaforo']);
        $carteira = (new IndiceRegularidadeService(new RelogioFixo(new DateTimeImmutable(self::AGORA))))
            ->calcularCarteira($this->escritorioA);
        $porCor = array_count_values(array_column($carteira, 'semaforo'));
        foreach ($painel['semaforo'] as $cor => $quantidade) {
            self::assertSame($porCor[$cor] ?? 0, $quantidade, $cor);
        }

        // Vermelha sem score primeiro; depois score crescente; empate por nome e por ID.
        self::assertSame([$zulu, $alfa1, $alfa2, $bravo, $meio], array_column($painel['piores'], 'empresa_id'));
        self::assertSame([null, 0, 0, 0, 50], array_column($painel['piores'], 'score'));
        self::assertNotContains($nova, array_column($painel['piores'], 'empresa_id'));
        foreach ($painel['piores'] as $pior) {
            self::assertSame('/empresas/' . $pior['empresa_id'], $pior['caminho']);
            self::assertSame($carteira[$pior['empresa_id']]['motivos'], $pior['motivos']);
            self::assertSame('vermelho', $pior['semaforo']);
            self::assertNotEmpty($pior['recomendacao']);
        }
        self::assertSame('Zulu Ltda', $painel['piores'][0]['nome']);
        self::assertStringContainsString('Ação imediata: regularizar', $painel['piores'][0]['recomendacao']);

        // "meus": só as empresas sob responsabilidade do colaborador (Bravo e Nova).
        $meus = $this->painel->montar($this->usuario($this->colaborador, 'COLABORADOR'), 'meus');
        self::assertSame(['verde' => 0, 'amarelo' => 0, 'vermelho' => 1, 'sem_historico' => 1], $meus['semaforo']);
        self::assertSame([$bravo], array_column($meus['piores'], 'empresa_id'));
    }

    public function testCargaMostraAEquipeInteiraEmQualquerEscopo(): void
    {
        $semObrigacoes = $this->cadastro->criarUsuario($this->escritorioA, 'COLABORADOR');
        $inativoComAtraso = $this->cadastro->criarUsuario($this->escritorioA, 'COLABORADOR', null, false);
        $inativoSoEntregue = $this->cadastro->criarUsuario($this->escritorioA, 'COLABORADOR', null, false);
        $empresa = $this->empresa('Empresa Carga');
        $this->obrigacao($empresa, '2026-03-17', 'PENDENTE', $this->admin);
        $this->obrigacao($empresa, '2026-03-09', 'PENDENTE', $this->colaborador);
        $this->obrigacao($empresa, '2026-03-12', 'EM_ANDAMENTO', $this->colaborador);
        $this->obrigacao($empresa, '2026-03-05', 'AGUARDANDO_CLIENTE', $inativoComAtraso);
        $this->obrigacao($empresa, '2026-03-05', 'ENTREGUE', $inativoSoEntregue, '2026-03-05 09:00:00');
        $this->obrigacao($empresa, '2024-12-10', 'PENDENTE', null);
        $this->obrigacao($empresa, '2026-02-10', 'ENTREGUE', null, '2026-02-10 09:00:00');

        $todos = $this->painel->montar($this->usuario($this->admin, 'ADMIN'), 'todos');
        $meus = $this->painel->montar($this->usuario($this->colaborador, 'COLABORADOR'), 'meus');

        $esperado = [
            [$this->admin, self::NOME_USUARIO, false, 0, 1],
            [$this->colaborador, self::NOME_USUARIO, false, 1, 1],
            [$semObrigacoes, self::NOME_USUARIO, false, 0, 0],
            [$inativoComAtraso, self::NOME_USUARIO . ' (inativo)', true, 1, 0],
            [null, 'Sem responsável', false, 1, 0],
        ];
        self::assertSame($esperado, $this->resumoDaCarga($todos));
        self::assertSame($esperado, $this->resumoDaCarga($meus));

        foreach ($todos['carga'] as $linha) {
            foreach (['atrasadas' => 'atrasada', 'vence_7d' => 'vence_7d'] as $coluna => $situacao) {
                if ($linha['responsavel_id'] === null) {
                    self::assertNull($linha[$coluna]['query'], 'A linha sem responsável não tem link.');
                    continue;
                }
                $query = ['responsavel' => (string) $linha['responsavel_id'], 'situacao' => $situacao];
                self::assertSame($query, $linha[$coluna]['query']);
                self::assertSame($this->totalDaListagem($query), $linha[$coluna]['valor']);
            }
        }
    }

    public function testSemResponsavelSoApareceComObrigacoesNasCategoriasExibidas(): void
    {
        $empresa = $this->empresa('Empresa Só Entregue');
        $this->obrigacao($empresa, '2026-02-10', 'ENTREGUE', null, '2026-02-10 09:00:00');

        $painel = $this->painel->montar($this->usuario($this->admin, 'ADMIN'), 'todos');

        self::assertNotContains(null, array_column($painel['carga'], 'responsavel_id'));
    }

    public function testCartoesRespeitamContratacaoEOrdem(): void
    {
        $modulos = [
            'base' => ['contratavel' => null, 'cartoes_painel' => [CartaoTesteA::class, CartaoTesteB::class]],
            'financeiro' => ['contratavel' => 'BPO_FINANCEIRO', 'cartoes_painel' => [CartaoTesteBpo::class]],
        ];

        $semModulo = $this->painel->cartoes($this->usuario($this->admin, 'ADMIN'), $modulos);
        $comModulo = $this->painel->cartoes($this->usuario($this->admin, 'ADMIN', null, ['BPO_FINANCEIRO']), $modulos);

        self::assertSame(['Cartão B', 'Cartão A'], array_column($semModulo, 'titulo'));
        self::assertSame(['Cartão BPO', 'Cartão B', 'Cartão A'], array_column($comModulo, 'titulo'));
        self::assertSame('teste/cartao', $semModulo[0]['template']);
        self::assertSame(['escritorio' => $this->escritorioA], $semModulo[0]['dados']);

        // O módulo real de documentos declara seu cartão no painel.
        $reais = (new Modulos(new Router()))->carregar();
        self::assertSame(['Documentos dos Clientes'], array_column($this->painel->cartoes($this->usuario($this->admin, 'ADMIN'), $reais), 'titulo'));
    }

    public function testQuantidadeDeConsultasNaoCresceComEmpresasEResponsaveis(): void
    {
        $empresa = $this->empresa('Pequena');
        $this->obrigacao($empresa, '2026-03-09', 'PENDENTE', $this->colaborador);
        $pequeno = $this->consultasDoPainel($this->escritorioA, $this->admin);

        $grande = $this->escritorioB;
        $adminB = $this->cadastro->criarUsuario($grande, 'ADMIN');
        for ($i = 0; $i < 8; $i++) {
            $responsavel = $this->cadastro->criarUsuario($grande, 'COLABORADOR', null, $i % 3 !== 0);
            $empresaB = $this->cadastro->criarEmpresa($grande, Cnpj::gerarFicticio(random_int(1, 99_999_999)), 'Grande ' . $i);
            foreach (['2026-03-09', '2026-03-12', '2025-12-10'] as $vencimento) {
                $this->fiscal->criarObrigacao($grande, $empresaB, $this->fiscal->criarTipo($grande), $adminB, [
                    'competencia' => substr($vencimento, 0, 8) . '01',
                    'vencimento' => $vencimento,
                    'responsavel_id' => $responsavel,
                ]);
            }
        }
        $grandeConsultas = $this->consultasDoPainel($grande, $adminB);

        self::assertSame($pequeno, $grandeConsultas);
        self::assertLessThanOrEqual(5, $pequeno);
    }

    public function testNadaDeOutroEscritorioEntra(): void
    {
        $this->obrigacaoDeOutroEscritorio('2026-03-09', 'PENDENTE');

        $painel = $this->painel->montar($this->usuario($this->admin, 'ADMIN'), 'todos');

        self::assertSame(
            ['vence_hoje' => 0, 'vence_7d' => 0, 'atrasadas' => 0, 'entregues_mes' => 0, 'entregues_no_prazo' => 0],
            $this->valores($painel)
        );
        self::assertSame(['verde' => 0, 'amarelo' => 0, 'vermelho' => 0, 'sem_historico' => 0], $painel['semaforo']);
        self::assertSame([], $painel['piores']);
        self::assertSame([$this->admin, $this->colaborador], array_column($painel['carga'], 'responsavel_id'));
    }

    /**
     * @param array<string, mixed> $painel
     */
    private function assertNumerosIguaisAListagem(array $painel, int $usuarioId): void
    {
        foreach ($painel['obrigacoes'] as $chave => $indicador) {
            $filtros = $indicador['query'];
            if (($filtros['responsavel'] ?? null) === 'meus') {
                $filtros['responsavel'] = (string) $usuarioId; // o controller da listagem faz a mesma troca
            }
            self::assertSame($this->totalDaListagem($filtros), $indicador['valor'], $chave);
        }
    }

    /**
     * @param array<string, string> $filtros
     */
    private function totalDaListagem(array $filtros): int
    {
        $obrigacoes = new Obrigacao(new RelogioFixo(new DateTimeImmutable(self::AGORA)));

        return $obrigacoes->listar(
            $this->escritorioA,
            $filtros,
            Paginacao::deRequest(new Request('GET', '/obrigacoes', ['pagina' => 1]), 1)
        )->total;
    }

    /**
     * @param array<string, mixed> $painel
     * @return array<string, int>
     */
    private function valores(array $painel): array
    {
        return array_map(static fn (array $indicador): int => $indicador['valor'], $painel['obrigacoes']);
    }

    /**
     * @param array<string, mixed> $painel
     * @return list<array{int|null, string, bool, int, int}>
     */
    private function resumoDaCarga(array $painel): array
    {
        return array_map(static fn (array $linha): array => [
            $linha['responsavel_id'],
            $linha['nome'],
            $linha['inativo'],
            $linha['atrasadas']['valor'],
            $linha['vence_7d']['valor'],
        ], $painel['carga']);
    }

    private function consultasDoPainel(int $escritorio, int $usuarioId): int
    {
        $usuario = $this->usuario($usuarioId, 'ADMIN', $escritorio);
        $antesDaMedicao = $this->questoes();
        $custoDaMedicao = $this->questoes() - $antesDaMedicao;

        $antes = $this->questoes();
        $this->painel->montar($usuario, 'todos');

        return $this->questoes() - $antes - $custoDaMedicao;
    }

    private function questoes(): int
    {
        $linha = $this->conexao()->query("SHOW SESSION STATUS LIKE 'Questions'")->fetch();

        return is_array($linha) ? (int) ($linha['Value'] ?? $linha['value'] ?? 0) : 0;
    }

    /**
     * @param list<string> $modulos
     */
    private function usuario(int $id, string $perfil, ?int $escritorio = null, array $modulos = []): UsuarioLogado
    {
        return new UsuarioLogado($id, $escritorio ?? $this->escritorioA, $perfil, null, 'Pessoa', $modulos);
    }

    private function empresa(string $razaoSocial, ?int $responsavel = null, string $status = 'ATIVA'): int
    {
        return $this->cadastro->criarEmpresa(
            $this->escritorioA,
            Cnpj::gerarFicticio(random_int(1, 99_999_999)),
            $razaoSocial,
            ['responsavel_id' => $responsavel, 'status' => $status]
        );
    }

    /**
     * Cada obrigação usa um tipo novo, para não esbarrar na chave única (empresa, tipo, competência).
     */
    private function obrigacao(int $empresa, string $vencimento, string $status, ?int $responsavel, ?string $entregueEm = null): int
    {
        return $this->fiscal->criarObrigacao(
            $this->escritorioA,
            $empresa,
            $this->fiscal->criarTipo($this->escritorioA),
            $this->admin,
            [
                'competencia' => substr($vencimento, 0, 8) . '01',
                'vencimento' => $vencimento,
                'status' => $status,
                'responsavel_id' => $responsavel,
                'entregue_em' => $entregueEm,
            ]
        );
    }

    private function obrigacaoDeOutroEscritorio(string $vencimento, string $status): void
    {
        $usuarioB = $this->cadastro->criarUsuario($this->escritorioB);
        $empresaB = $this->cadastro->criarEmpresa(
            $this->escritorioB,
            Cnpj::gerarFicticio(random_int(1, 99_999_999)),
            'Empresa de fora'
        );
        $this->fiscal->criarObrigacao($this->escritorioB, $empresaB, $this->fiscal->criarTipo($this->escritorioB), $usuarioB, [
            'competencia' => substr($vencimento, 0, 8) . '01',
            'vencimento' => $vencimento,
            'status' => $status,
            'responsavel_id' => $usuarioB,
        ]);
    }
}

/**
 * Cartões de teste: implementam o contrato real de App\Core\CartaoPainel.
 */
abstract class CartaoTesteBase implements CartaoPainel
{
    public function dados(UsuarioLogado $usuario): array
    {
        return ['escritorio' => $usuario->escritorioId];
    }

    public function template(): string
    {
        return 'teste/cartao';
    }
}

final class CartaoTesteA extends CartaoTesteBase
{
    public function titulo(): string
    {
        return 'Cartão A';
    }

    public function ordem(): int
    {
        return 20;
    }
}

final class CartaoTesteB extends CartaoTesteBase
{
    public function titulo(): string
    {
        return 'Cartão B';
    }

    public function ordem(): int
    {
        return 10;
    }
}

final class CartaoTesteBpo extends CartaoTesteBase
{
    public function titulo(): string
    {
        return 'Cartão BPO';
    }

    public function ordem(): int
    {
        return 5;
    }
}
