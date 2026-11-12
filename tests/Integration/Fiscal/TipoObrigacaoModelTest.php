<?php

declare(strict_types=1);

namespace Tests\Integration\Fiscal;

use App\Core\Paginacao;
use App\Core\Request;
use App\Models\Fiscal\TipoObrigacao;
use App\Support\Cnpj;
use PDOException;
use Tests\Integration\IntegrationTestCase;
use Tests\Support\FabricaCadastro;
use Tests\Support\FabricaFiscal;

final class TipoObrigacaoModelTest extends IntegrationTestCase
{
    private TipoObrigacao $tipos;

    private FabricaCadastro $cadastro;

    private FabricaFiscal $fiscal;

    private int $escritorioA;

    private int $escritorioB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tipos = new TipoObrigacao();
        $this->cadastro = new FabricaCadastro($this->conexao());
        $this->fiscal = new FabricaFiscal($this->conexao());
        $this->escritorioA = $this->cadastro->criarEscritorio();
        $this->escritorioB = $this->cadastro->criarEscritorio();
    }

    public function testCriaBuscaAtualizaEExcluiTipo(): void
    {
        $id = $this->tipos->criar($this->escritorioA, $this->dados('Guia mensal'));

        $tipo = $this->tipos->buscar($this->escritorioA, $id);
        self::assertNotNull($tipo);
        self::assertSame('Guia mensal', $tipo['nome']);
        self::assertSame('MENSAL', $tipo['periodicidade']);
        self::assertSame(20, (int) $tipo['dia_vencimento']);

        $this->tipos->atualizar($this->escritorioA, $id, $this->dados('Declaração anual', [
            'periodicidade' => 'ANUAL',
            'dia_vencimento' => null,
            'meses_apos_competencia' => 5,
            'ativo' => 0,
        ]));
        $atualizado = $this->tipos->buscar($this->escritorioA, $id);
        self::assertNotNull($atualizado);
        self::assertSame('Declaração anual', $atualizado['nome']);
        self::assertNull($atualizado['dia_vencimento']);
        self::assertSame(5, (int) $atualizado['meses_apos_competencia']);
        self::assertSame(0, (int) $atualizado['ativo']);

        self::assertSame(1, $this->tipos->excluir($this->escritorioA, $id));
        self::assertNull($this->tipos->buscar($this->escritorioA, $id));
    }

    public function testTipoDeOutroEscritorioNaoEhLidoAlteradoNemExcluido(): void
    {
        $id = $this->tipos->criar($this->escritorioA, $this->dados('Privado A'));

        self::assertNull($this->tipos->buscar($this->escritorioB, $id));

        $this->tipos->atualizar($this->escritorioB, $id, $this->dados('Invasão B'));
        self::assertSame(0, $this->tipos->excluir($this->escritorioB, $id));

        $tipo = $this->tipos->buscar($this->escritorioA, $id);
        self::assertNotNull($tipo);
        self::assertSame('Privado A', $tipo['nome']);
    }

    public function testListarCombinaFiltrosEscapaLikeENaoVazaOutroEscritorio(): void
    {
        $texto = 'Guia 20%_A\\B';
        $alvo = $this->tipos->criar($this->escritorioA, $this->dados($texto . ' alvo', ['esfera' => 'MUNICIPAL']));
        $this->tipos->criar($this->escritorioA, $this->dados($texto . ' inativo', [
            'esfera' => 'MUNICIPAL',
            'ativo' => 0,
        ]));
        $this->tipos->criar($this->escritorioA, $this->dados($texto . ' federal', ['esfera' => 'FEDERAL']));
        $this->tipos->criar($this->escritorioA, $this->dados($texto . ' trimestral', [
            'esfera' => 'MUNICIPAL',
            'periodicidade' => 'TRIMESTRAL',
        ]));
        // Sem os caracteres especiais: só apareceria se o LIKE não estivesse escapado.
        $this->tipos->criar($this->escritorioA, $this->dados('Guia 20xxAyB curinga', ['esfera' => 'MUNICIPAL']));
        $this->tipos->criar($this->escritorioB, $this->dados($texto . ' alvo', ['esfera' => 'MUNICIPAL']));

        $pagina = $this->tipos->listar($this->escritorioA, [
            'q' => '20%_A\\B',
            'esfera' => 'MUNICIPAL',
            'periodicidade' => 'MENSAL',
            'ativo' => '1',
        ], $this->paginacao(1));

        self::assertSame([$alvo], $this->ids($pagina->itens));
        self::assertSame(1, $pagina->total);
    }

    public function testFiltrosInvalidosSaoIgnorados(): void
    {
        $this->tipos->criar($this->escritorioA, $this->dados('Tipo A'));
        $this->tipos->criar($this->escritorioA, $this->dados('Tipo B', ['ativo' => 0]));

        $pagina = $this->tipos->listar($this->escritorioA, [
            'esfera' => 'INEXISTENTE',
            'periodicidade' => "MENSAL' OR 1=1",
            'ativo' => '2',
        ], $this->paginacao(1));

        self::assertSame(2, $pagina->total);
    }

    public function testListarOrdenaPorNomePaginaEContaEmpresasVinculadas(): void
    {
        $ids = [];
        for ($indice = 21; $indice >= 1; $indice--) {
            $ids[$indice] = $this->tipos->criar(
                $this->escritorioA,
                $this->dados(sprintf('Tipo %02d', $indice))
            );
        }
        $empresa = $this->cadastro->criarEmpresa($this->escritorioA, Cnpj::gerarFicticio(30000001), 'Empresa A');
        $this->fiscal->criarVinculo($this->escritorioA, $empresa, $ids[1]);

        $primeira = $this->tipos->listar($this->escritorioA, [], $this->paginacao(1));
        self::assertSame(21, $primeira->total);
        self::assertSame(2, $primeira->totalPaginas);
        self::assertSame($ids[1], (int) $primeira->itens[0]['id']);
        self::assertSame(1, (int) $primeira->itens[0]['total_empresas']);
        self::assertSame(0, (int) $primeira->itens[1]['total_empresas']);

        $segunda = $this->tipos->listar($this->escritorioA, [], $this->paginacao(2));
        self::assertSame([$ids[21]], $this->ids($segunda->itens));
    }

    public function testNomeJaExisteRespeitaEscritorioMaiusculasEIgnorarId(): void
    {
        $id = $this->tipos->criar($this->escritorioA, $this->dados('Folha de pagamento'));

        self::assertTrue($this->tipos->nomeJaExiste($this->escritorioA, 'FOLHA DE PAGAMENTO'));
        self::assertFalse($this->tipos->nomeJaExiste($this->escritorioA, 'Folha de pagamento', $id));
        self::assertFalse($this->tipos->nomeJaExiste($this->escritorioB, 'Folha de pagamento'));
    }

    public function testExcluirTipoComObrigacoesEhBloqueadoPeloBanco(): void
    {
        $id = $this->tipos->criar($this->escritorioA, $this->dados('Tipo usado'));
        $usuario = $this->cadastro->criarUsuario($this->escritorioA);
        $empresa = $this->cadastro->criarEmpresa($this->escritorioA, Cnpj::gerarFicticio(30000002), 'Empresa A');
        $this->fiscal->criarObrigacao($this->escritorioA, $empresa, $id, $usuario);

        try {
            $this->tipos->excluir($this->escritorioA, $id);
            self::fail('O banco deveria bloquear a exclusão de um tipo com obrigações.');
        } catch (PDOException $erro) {
            self::assertSame(1451, (int) ($erro->errorInfo[1] ?? 0));
        }
    }

    /**
     * @param array<string, mixed> $opcoes
     * @return array<string, mixed>
     */
    private function dados(string $nome, array $opcoes = []): array
    {
        return array_merge([
            'nome' => $nome,
            'descricao' => null,
            'esfera' => 'FEDERAL',
            'periodicidade' => 'MENSAL',
            'dia_vencimento' => 20,
            'meses_apos_competencia' => 1,
            'ativo' => 1,
        ], $opcoes);
    }

    private function paginacao(int $pagina): Paginacao
    {
        return Paginacao::deRequest(new Request('GET', '/tipos-obrigacao', ['pagina' => $pagina]), 20);
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
