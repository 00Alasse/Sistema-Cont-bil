<?php

declare(strict_types=1);

namespace Tests\Integration\Fiscal;

use App\Core\Pagina;
use App\Core\Paginacao;
use App\Core\Request;
use App\Exceptions\ConflitoException;
use App\Models\Fiscal\Obrigacao;
use App\Support\Cnpj;
use App\Support\RelogioFixo;
use DateTimeImmutable;
use PDOException;
use Tests\Integration\IntegrationTestCase;
use Tests\Support\FabricaCadastro;
use Tests\Support\FabricaFiscal;

/**
 * "Hoje" é sempre 10/03/2026 (RelogioFixo), salvo quando o teste troca o relógio.
 */
final class ObrigacaoModelTest extends IntegrationTestCase
{
    private const HOJE = '2026-03-10';

    private Obrigacao $obrigacoes;

    private FabricaCadastro $cadastro;

    private FabricaFiscal $fiscal;

    private int $escritorioA;

    private int $escritorioB;

    private int $usuarioA;

    private int $empresaA;

    private int $sequenciaCnpj = 40000000;

    protected function setUp(): void
    {
        parent::setUp();
        $this->obrigacoes = $this->modelEm(self::HOJE);
        $this->cadastro = new FabricaCadastro($this->conexao());
        $this->fiscal = new FabricaFiscal($this->conexao());
        $this->escritorioA = $this->cadastro->criarEscritorio();
        $this->escritorioB = $this->cadastro->criarEscritorio();
        $this->usuarioA = $this->cadastro->criarUsuario($this->escritorioA);
        $this->empresaA = $this->empresa($this->escritorioA, 'Empresa Alfa');
    }

    public function testCriaBuscaAtualizaEExcluiObrigacao(): void
    {
        $tipo = $this->fiscal->criarTipo($this->escritorioA, ['nome' => 'Guia mensal']);
        $id = $this->obrigacoes->criar($this->escritorioA, [
            'empresa_id' => $this->empresaA,
            'tipo_obrigacao_id' => $tipo,
            'competencia' => '2026-02-01',
            'vencimento' => '2026-03-20',
            'responsavel_id' => $this->usuarioA,
            'observacao' => 'Conferir guia',
            'criado_por' => $this->usuarioA,
        ]);

        $obrigacao = $this->obrigacoes->buscar($this->escritorioA, $id);
        self::assertNotNull($obrigacao);
        self::assertSame('Empresa Alfa', $obrigacao['empresa_nome']);
        self::assertSame('Guia mensal', $obrigacao['tipo_nome']);
        self::assertSame('Usuário C01 de teste', $obrigacao['responsavel_nome']);
        self::assertSame('2026-02-01', $obrigacao['competencia']);
        self::assertSame('PENDENTE', $obrigacao['status']);
        self::assertSame(1, (int) $obrigacao['versao']);
        self::assertSame($this->usuarioA, (int) $obrigacao['criado_por']);
        self::assertSame(0, (int) $obrigacao['atrasada']);

        $this->obrigacoes->atualizar($this->escritorioA, $id, [
            'vencimento' => '2026-03-05',
            'responsavel_id' => null,
            'observacao' => null,
        ], 1);

        $atualizada = $this->obrigacoes->buscar($this->escritorioA, $id);
        self::assertNotNull($atualizada);
        self::assertSame('2026-03-05', $atualizada['vencimento']);
        self::assertNull($atualizada['responsavel_id']);
        self::assertNull($atualizada['observacao']);
        self::assertSame(2, (int) $atualizada['versao']);
        // A edição não mexe no status (isso é a FI04).
        self::assertSame('PENDENTE', $atualizada['status']);
        self::assertSame(1, (int) $atualizada['atrasada']);

        self::assertSame(1, $this->obrigacoes->excluir($this->escritorioA, $id));
        self::assertNull($this->obrigacoes->buscar($this->escritorioA, $id));
    }

    public function testAtualizarComVersaoDesatualizadaLancaConflito(): void
    {
        $id = $this->nova();
        $this->obrigacoes->atualizar($this->escritorioA, $id, $this->edicao('2026-03-25'), 1);

        try {
            $this->obrigacoes->atualizar($this->escritorioA, $id, $this->edicao('2026-03-28'), 1);
            self::fail('A versão 1 já estava desatualizada.');
        } catch (ConflitoException) {
        }

        $obrigacao = $this->obrigacoes->buscar($this->escritorioA, $id);
        self::assertNotNull($obrigacao);
        self::assertSame('2026-03-25', $obrigacao['vencimento']);
        self::assertSame(2, (int) $obrigacao['versao']);
    }

    public function testObrigacaoDeOutroEscritorioNaoEhLidaAlteradaNemExcluida(): void
    {
        $id = $this->nova(['vencimento' => '2026-03-20']);

        self::assertNull($this->obrigacoes->buscar($this->escritorioB, $id));
        try {
            $this->obrigacoes->atualizar($this->escritorioB, $id, $this->edicao('2026-04-30'), 1);
            self::fail('Outro escritório não pode atualizar a obrigação.');
        } catch (ConflitoException) {
        }
        self::assertSame(0, $this->obrigacoes->excluir($this->escritorioB, $id));
        self::assertSame([], $this->ids($this->obrigacoes->listar($this->escritorioB, [], $this->paginacao())->itens));

        $obrigacao = $this->obrigacoes->buscar($this->escritorioA, $id);
        self::assertNotNull($obrigacao);
        self::assertSame('2026-03-20', $obrigacao['vencimento']);
        self::assertSame(1, (int) $obrigacao['versao']);
    }

    public function testListarNaoMisturaEscritorios(): void
    {
        $propria = $this->nova();
        $empresaB = $this->empresa($this->escritorioB, 'Empresa Beta');
        $usuarioB = $this->cadastro->criarUsuario($this->escritorioB);
        $this->fiscal->criarObrigacao(
            $this->escritorioB,
            $empresaB,
            $this->fiscal->criarTipo($this->escritorioB),
            $usuarioB
        );

        $pagina = $this->obrigacoes->listar($this->escritorioA, [], $this->paginacao());

        self::assertSame([$propria], $this->ids($pagina->itens));
        self::assertSame(1, $pagina->total);
    }

    public function testDuplicataDeTipoNaCompetenciaEhBloqueadaPeloBanco(): void
    {
        $tipo = $this->fiscal->criarTipo($this->escritorioA);
        $dados = [
            'empresa_id' => $this->empresaA,
            'tipo_obrigacao_id' => $tipo,
            'competencia' => '2026-02-01',
            'vencimento' => '2026-03-20',
            'responsavel_id' => null,
            'observacao' => null,
            'criado_por' => $this->usuarioA,
        ];
        self::assertFalse($this->obrigacoes->jaExiste($this->escritorioA, $this->empresaA, $tipo, '2026-02-01'));
        $this->obrigacoes->criar($this->escritorioA, $dados);
        self::assertTrue($this->obrigacoes->jaExiste($this->escritorioA, $this->empresaA, $tipo, '2026-02-01'));
        self::assertFalse($this->obrigacoes->jaExiste($this->escritorioA, $this->empresaA, $tipo, '2026-03-01'));
        self::assertFalse($this->obrigacoes->jaExiste($this->escritorioB, $this->empresaA, $tipo, '2026-02-01'));

        try {
            $this->obrigacoes->criar($this->escritorioA, $dados);
            self::fail('O banco deveria recusar a mesma obrigação na mesma competência.');
        } catch (PDOException $erro) {
            self::assertSame(1062, (int) ($erro->errorInfo[1] ?? 0));
            self::assertStringContainsString('uq_obr_competencia', (string) ($erro->errorInfo[2] ?? ''));
        }
    }

    public function testBuscaPorEmpresaOuTipoComLikeEscapado(): void
    {
        $empresaEspecial = $this->empresa($this->escritorioA, 'Padaria 50%_Pão\\Doce');
        $porEmpresa = $this->nova([], $empresaEspecial);
        $tipoEspecial = $this->fiscal->criarTipo($this->escritorioA, ['nome' => 'Guia 50%_Pão\\Doce']);
        $porTipo = $this->nova([], $this->empresaA, $tipoEspecial);
        // Sem os caracteres especiais: só apareceria se o LIKE não estivesse escapado.
        $curinga = $this->empresa($this->escritorioA, 'Padaria 50xxPãoyDoce');
        $this->nova([], $curinga);
        $this->nova();

        $pagina = $this->listar(['q' => '50%_Pão\\Doce', 'ordem' => 'empresa']);

        self::assertEqualsCanonicalizing([$porEmpresa, $porTipo], $this->ids($pagina->itens));
    }

    public function testFiltraPorEmpresaTipoStatusResponsavelECompetencia(): void
    {
        $outraEmpresa = $this->empresa($this->escritorioA, 'Empresa Gama');
        $tipo = $this->fiscal->criarTipo($this->escritorioA);
        $responsavel = $this->cadastro->criarUsuario($this->escritorioA, 'COLABORADOR');
        $alvo = $this->nova([
            'competencia' => '2026-02-01',
            'status' => 'EM_ANDAMENTO',
            'responsavel_id' => $responsavel,
        ], $outraEmpresa, $tipo);
        $mesmoTipoOutraCompetencia = $this->nova(['competencia' => '2026-01-01'], $outraEmpresa, $tipo);
        $outra = $this->nova(['competencia' => '2026-02-01']);

        self::assertEqualsCanonicalizing(
            [$alvo, $mesmoTipoOutraCompetencia],
            $this->ids($this->listar(['empresa_id' => (string) $outraEmpresa])->itens)
        );
        self::assertEqualsCanonicalizing(
            [$alvo, $mesmoTipoOutraCompetencia],
            $this->ids($this->listar(['tipo_id' => (string) $tipo])->itens)
        );
        self::assertSame([$alvo], $this->ids($this->listar(['status' => 'EM_ANDAMENTO'])->itens));
        self::assertSame([$alvo], $this->ids($this->listar(['responsavel' => (string) $responsavel])->itens));
        self::assertEqualsCanonicalizing(
            [$alvo, $outra],
            $this->ids($this->listar(['competencia' => '2026-02'])->itens)
        );
        self::assertSame([$alvo], $this->ids($this->listar([
            'empresa_id' => (string) $outraEmpresa,
            'competencia' => '2026-02',
            'status' => 'EM_ANDAMENTO',
        ])->itens));
    }

    public function testFiltraPorPeriodoDeVencimento(): void
    {
        $antes = $this->nova(['vencimento' => '2026-03-04']);
        $inicio = $this->nova(['vencimento' => '2026-03-05']);
        $fim = $this->nova(['vencimento' => '2026-03-15']);
        $depois = $this->nova(['vencimento' => '2026-03-16']);

        self::assertEqualsCanonicalizing(
            [$inicio, $fim],
            $this->ids($this->listar(['de' => '2026-03-05', 'ate' => '2026-03-15'])->itens)
        );
        self::assertEqualsCanonicalizing(
            [$fim, $depois],
            $this->ids($this->listar(['de' => '2026-03-15'])->itens)
        );
        self::assertEqualsCanonicalizing(
            [$antes, $inicio],
            $this->ids($this->listar(['ate' => '2026-03-05'])->itens)
        );
    }

    public function testSituacoesDerivadasUsamAHojeDoRelogio(): void
    {
        $atrasada = $this->nova(['vencimento' => '2026-03-09']);
        $aguardandoAtrasada = $this->nova(['vencimento' => '2026-03-01', 'status' => 'AGUARDANDO_CLIENTE']);
        $venceHoje = $this->nova(['vencimento' => self::HOJE, 'status' => 'EM_ANDAMENTO']);
        $venceEm7 = $this->nova(['vencimento' => '2026-03-17', 'status' => 'AGUARDANDO_CLIENTE']);
        $venceEm8 = $this->nova(['vencimento' => '2026-03-18']);
        $entregueNoPrazo = $this->nova([
            'vencimento' => '2026-03-05',
            'status' => 'ENTREGUE',
            'entregue_em' => '2026-03-05 23:59:00',
        ]);
        $entregueComAtraso = $this->nova([
            'vencimento' => '2026-03-05',
            'status' => 'ENTREGUE',
            'entregue_em' => '2026-03-06 08:00:00',
        ]);
        // Vencidas, mas fechadas: não contam como atrasadas.
        $this->nova(['vencimento' => '2026-03-01', 'status' => 'DISPENSADA']);
        $entregueHoje = $this->nova([
            'vencimento' => self::HOJE,
            'status' => 'ENTREGUE',
            'entregue_em' => self::HOJE . ' 09:00:00',
        ]);

        self::assertEqualsCanonicalizing([$atrasada, $aguardandoAtrasada], $this->idsDaSituacao('atrasada'));
        self::assertSame([$venceHoje], $this->idsDaSituacao('vence_hoje'));
        self::assertEqualsCanonicalizing([$venceHoje, $venceEm7], $this->idsDaSituacao('vence_7d'));
        self::assertSame([$entregueComAtraso], $this->idsDaSituacao('entregue_atraso'));

        $colunas = [];
        foreach ($this->listar([])->itens as $item) {
            $colunas[(int) $item['id']] = [(int) $item['atrasada'], (int) $item['entregue_com_atraso']];
        }
        self::assertSame([1, 0], $colunas[$atrasada]);
        self::assertSame([0, 0], $colunas[$venceHoje]);
        self::assertSame([0, 0], $colunas[$venceEm8]);
        self::assertSame([0, 0], $colunas[$entregueNoPrazo]);
        self::assertSame([0, 1], $colunas[$entregueComAtraso]);
        self::assertSame([0, 0], $colunas[$entregueHoje]);

        // Com outro "hoje", as mesmas obrigações mudam de situação: nada é gravado.
        $this->obrigacoes = $this->modelEm('2026-03-11');
        self::assertEqualsCanonicalizing(
            [$atrasada, $aguardandoAtrasada, $venceHoje],
            $this->idsDaSituacao('atrasada')
        );
        self::assertEqualsCanonicalizing([$venceEm7, $venceEm8], $this->idsDaSituacao('vence_7d'));
        self::assertSame([], $this->idsDaSituacao('vence_hoje'));
    }

    public function testFiltrosInvalidosSaoIgnorados(): void
    {
        $this->nova();
        $this->nova(['status' => 'ENTREGUE']);

        $pagina = $this->listar([
            'empresa_id' => '1 OR 1=1',
            'tipo_id' => 'abc',
            'status' => "PENDENTE' OR '1'='1",
            'situacao' => 'qualquer',
            'responsavel' => '-1',
            'competencia' => '2026-13',
            'de' => '2026-02-30',
            'ate' => '10/03/2026',
            'ordem' => 'o.id; DROP TABLE obrigacoes',
        ]);

        self::assertSame(2, $pagina->total);
    }

    public function testOrdenaPorVencimentoCompetenciaOuEmpresa(): void
    {
        $empresaZeta = $this->empresa($this->escritorioA, 'Zeta Comércio');
        $primeira = $this->nova(['competencia' => '2026-02-01', 'vencimento' => '2026-03-20'], $empresaZeta);
        $segunda = $this->nova(['competencia' => '2026-01-01', 'vencimento' => '2026-03-25']);
        $terceira = $this->nova(['competencia' => '2026-03-01', 'vencimento' => '2026-04-01']);

        self::assertSame([$primeira, $segunda, $terceira], $this->ids($this->listar([])->itens));
        self::assertSame([$terceira, $segunda, $primeira], $this->ids($this->listar(['ordem' => '-vencimento'])->itens));
        self::assertSame([$segunda, $primeira, $terceira], $this->ids($this->listar(['ordem' => 'competencia'])->itens));
        self::assertSame([$terceira, $primeira, $segunda], $this->ids($this->listar(['ordem' => '-competencia'])->itens));
        self::assertSame($primeira, $this->ids($this->listar(['ordem' => '-empresa'])->itens)[0]);
        self::assertSame($primeira, $this->ids($this->listar(['ordem' => 'empresa'])->itens)[2]);
    }

    public function testOpcoesDeEmpresasETiposSaoDoProprioEscritorio(): void
    {
        $ativo = $this->fiscal->criarTipo($this->escritorioA, ['nome' => 'Tipo ativo A']);
        $inativo = $this->fiscal->criarTipo($this->escritorioA, ['nome' => 'Tipo inativo A', 'ativo' => 0]);
        $this->fiscal->criarTipo($this->escritorioB, ['nome' => 'Tipo de B']);
        $this->empresa($this->escritorioB, 'Empresa Beta');

        $empresas = $this->obrigacoes->opcoesEmpresas($this->escritorioA);
        self::assertSame([['id' => $this->empresaA, 'nome' => 'Empresa Alfa']], $empresas);

        $todos = array_column($this->obrigacoes->opcoesTipos($this->escritorioA), 'id');
        self::assertEqualsCanonicalizing([$ativo, $inativo], $todos);
        self::assertSame(
            [['id' => $ativo, 'nome' => 'Tipo ativo A']],
            $this->obrigacoes->opcoesTipos($this->escritorioA, true)
        );
    }

    private function modelEm(string $hoje): Obrigacao
    {
        return new Obrigacao(new RelogioFixo(new DateTimeImmutable($hoje . ' 14:30:00')));
    }

    private function empresa(int $escritorioId, string $razaoSocial): int
    {
        return $this->cadastro->criarEmpresa(
            $escritorioId,
            Cnpj::gerarFicticio($this->sequenciaCnpj++),
            $razaoSocial
        );
    }

    /**
     * Cada chamada usa um tipo novo, para não esbarrar na chave única (empresa, tipo, competência).
     *
     * @param array<string, mixed> $opcoes
     */
    private function nova(array $opcoes = [], ?int $empresaId = null, ?int $tipoId = null): int
    {
        return $this->fiscal->criarObrigacao(
            $this->escritorioA,
            $empresaId ?? $this->empresaA,
            $tipoId ?? $this->fiscal->criarTipo($this->escritorioA),
            $this->usuarioA,
            $opcoes
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function edicao(string $vencimento): array
    {
        return ['vencimento' => $vencimento, 'responsavel_id' => null, 'observacao' => null];
    }

    /**
     * @param array<string, string> $filtros
     */
    private function listar(array $filtros): Pagina
    {
        return $this->obrigacoes->listar($this->escritorioA, $filtros, $this->paginacao());
    }

    /**
     * @return list<int>
     */
    private function idsDaSituacao(string $situacao): array
    {
        return $this->ids($this->listar(['situacao' => $situacao])->itens);
    }

    private function paginacao(): Paginacao
    {
        return Paginacao::deRequest(new Request('GET', '/obrigacoes', ['pagina' => 1]), 20);
    }

    /**
     * @param list<array<string, mixed>> $itens
     * @return list<int>
     */
    private function ids(array $itens): array
    {
        return array_map(static fn (array $item): int => (int) $item['id'], $itens);
    }
}
