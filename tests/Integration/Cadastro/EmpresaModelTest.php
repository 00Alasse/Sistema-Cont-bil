<?php

declare(strict_types=1);

namespace Tests\Integration\Cadastro;

use App\Core\Pagina;
use App\Core\Paginacao;
use App\Core\Request;
use App\Exceptions\ConflitoException;
use App\Models\Cadastro\Empresa;
use App\Support\Cnpj;
use PDOException;
use Tests\Integration\IntegrationTestCase;
use Tests\Support\FabricaCadastro;

final class EmpresaModelTest extends IntegrationTestCase
{
    private FabricaCadastro $fabrica;

    private Empresa $empresas;

    private int $escritorioA;

    private int $escritorioB;

    private int $responsavelA;

    private int $responsavelB;

    private int $sementeCnpj = 10000000;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fabrica = new FabricaCadastro($this->conexao());
        $this->empresas = new Empresa();
        $this->escritorioA = $this->fabrica->criarEscritorio();
        $this->escritorioB = $this->fabrica->criarEscritorio();
        $this->responsavelA = $this->fabrica->criarUsuario($this->escritorioA, 'COLABORADOR');
        $this->responsavelB = $this->fabrica->criarUsuario($this->escritorioB, 'ADMIN');
    }

    public function testCriaBuscaAtualizaVersaoEExcluiEmpresa(): void
    {
        $dados = $this->dadosEmpresa('Empresa C01');
        $id = $this->empresas->criar($this->escritorioA, $dados);

        $empresa = $this->empresas->buscar($this->escritorioA, $id);
        self::assertNotNull($empresa);
        self::assertSame('Empresa C01', $empresa['razao_social']);
        self::assertSame($dados['cnpj'], $empresa['cnpj']);
        self::assertSame(1, (int) $empresa['versao']);

        $dados['razao_social'] = 'Empresa C01 Atualizada';
        $this->empresas->atualizar($this->escritorioA, $id, $dados, 1);
        $atualizada = $this->empresas->buscar($this->escritorioA, $id);
        self::assertNotNull($atualizada);
        self::assertSame('Empresa C01 Atualizada', $atualizada['razao_social']);
        self::assertSame(2, (int) $atualizada['versao']);

        $this->empresas->excluir($this->escritorioA, $id);
        self::assertNull($this->empresas->buscar($this->escritorioA, $id));
    }

    public function testRegistroDeOutroEscritorioNaoPodeSerConsultadoOuAtualizado(): void
    {
        $id = $this->empresas->criar($this->escritorioA, $this->dadosEmpresa('Privada A'));

        self::assertNull($this->empresas->buscar($this->escritorioB, $id));

        try {
            $this->empresas->atualizar(
                $this->escritorioB,
                $id,
                $this->dadosEmpresa('Tentativa B'),
                1
            );
            self::fail('Atualizar uma empresa de outro escritório deveria gerar conflito.');
        } catch (ConflitoException) {
            $this->addToAssertionCount(1);
        }

        self::assertSame(0, $this->empresas->excluir($this->escritorioB, $id));
        $empresa = $this->empresas->buscar($this->escritorioA, $id);
        self::assertNotNull($empresa);
        self::assertSame('Privada A', $empresa['razao_social']);
    }

    public function testDuplicidadeDeCnpjEPermitidaSomenteEmOutroEscritorio(): void
    {
        $cnpj = $this->novoCnpj();
        $this->empresas->criar($this->escritorioA, $this->dadosEmpresa('Empresa A', $cnpj));

        try {
            $this->empresas->criar($this->escritorioA, $this->dadosEmpresa('Duplicada A', $cnpj));
            self::fail('A restrição única deve impedir CNPJ repetido no mesmo escritório.');
        } catch (PDOException $erro) {
            self::assertSame('23000', (string) $erro->getCode());
        }

        $idOutroEscritorio = $this->empresas->criar(
            $this->escritorioB,
            $this->dadosEmpresa('Empresa B', $cnpj, $this->responsavelB)
        );
        self::assertNotNull($this->empresas->buscar($this->escritorioB, $idOutroEscritorio));
    }

    public function testAtualizacaoComVersaoAntigaGeraConflitoSemSobrescreverDados(): void
    {
        $dados = $this->dadosEmpresa('Versão original');
        $id = $this->empresas->criar($this->escritorioA, $dados);
        $dados['razao_social'] = 'Primeira atualização';
        $this->empresas->atualizar($this->escritorioA, $id, $dados, 1);

        $dados['razao_social'] = 'Edição desatualizada';
        $this->expectException(ConflitoException::class);
        $this->empresas->atualizar($this->escritorioA, $id, $dados, 1);
    }

    public function testValidaResponsavelAtivoDoMesmoEscritorioEPerfilPermitido(): void
    {
        $inativo = $this->fabrica->criarUsuario($this->escritorioA, 'COLABORADOR', null, false);
        $empresaId = $this->empresas->criar(
            $this->escritorioA,
            $this->dadosEmpresa('Empresa do cliente')
        );
        $cliente = $this->fabrica->criarUsuario($this->escritorioA, 'CLIENTE', $empresaId);

        self::assertTrue($this->empresas->responsavelValido($this->escritorioA, $this->responsavelA));
        self::assertFalse($this->empresas->responsavelValido($this->escritorioA, $this->responsavelB));
        self::assertFalse($this->empresas->responsavelValido($this->escritorioA, $inativo));
        self::assertFalse($this->empresas->responsavelValido($this->escritorioA, $cliente));
    }

    public function testBuscaCombinaBuscaFiltrosEServicoSemVazarOutroEscritorio(): void
    {
        $texto = 'Cliente 20%_A\\B';
        $alvo = $this->empresas->criar(
            $this->escritorioA,
            $this->dadosEmpresa($texto, $this->novoCnpj(), $this->responsavelA, [
                'regime_tributario' => 'MEI',
                'bpo_ativo' => 1,
            ])
        );
        $inativa = $this->empresas->criar(
            $this->escritorioA,
            $this->dadosEmpresa($texto, $this->novoCnpj(), $this->responsavelA, [
                'regime_tributario' => 'MEI',
                'bpo_ativo' => 1,
            ])
        );
        $stmt = $this->conexao()->prepare('UPDATE empresas SET status = :status WHERE id = :id');
        $stmt->execute(['status' => 'INATIVA', 'id' => $inativa]);
        $this->empresas->criar(
            $this->escritorioA,
            $this->dadosEmpresa($texto, $this->novoCnpj(), $this->responsavelA, [
                'regime_tributario' => 'LUCRO_REAL',
                'bpo_ativo' => 1,
            ])
        );
        $this->empresas->criar(
            $this->escritorioB,
            $this->dadosEmpresa($texto, $this->novoCnpj(), $this->responsavelB, [
                'regime_tributario' => 'MEI',
                'bpo_ativo' => 1,
            ])
        );

        $pagina = $this->empresas->listar($this->escritorioA, [
            'q' => '20%_A\\B',
            'status' => 'ATIVA',
            'regime' => 'MEI',
            'responsavel_id' => (string) $this->responsavelA,
            'servico' => 'bpo',
        ], $this->paginacao(1, 20));

        self::assertSame([$alvo], array_map(
            static fn (array $empresa): int => (int) $empresa['id'],
            $pagina->itens
        ));
        self::assertSame(1, $pagina->total);
    }

    public function testBuscaTambemLocalizaNomeFantasiaECnpj(): void
    {
        $cnpj = $this->novoCnpj();
        $id = $this->empresas->criar(
            $this->escritorioA,
            $this->dadosEmpresa('Razão social independente', $cnpj, $this->responsavelA, [
                'nome_fantasia' => 'Fantasia para pesquisa',
            ])
        );

        foreach (['Fantasia para pesquisa', substr($cnpj, 0, 5)] as $termo) {
            $pagina = $this->empresas->listar(
                $this->escritorioA,
                ['q' => $termo],
                $this->paginacao(1, 20)
            );
            self::assertSame([$id], array_map(
                static fn (array $empresa): int => (int) $empresa['id'],
                $pagina->itens
            ));
        }
    }

    public function testOrdenaPorRazaoSocialEDataDeCriacaoEPaginaResultados(): void
    {
        $ids = [];
        foreach (['Empresa C', 'Empresa A', 'Empresa B'] as $nome) {
            $ids[$nome] = $this->empresas->criar($this->escritorioA, $this->dadosEmpresa($nome));
        }

        $pagina = $this->empresas->listar(
            $this->escritorioA,
            ['ordem' => 'razao_social'],
            $this->paginacao(2, 2)
        );
        self::assertInstanceOf(Pagina::class, $pagina);
        self::assertSame(3, $pagina->total);
        self::assertSame(2, $pagina->totalPaginas);
        self::assertSame([$ids['Empresa C']], array_map(
            static fn (array $empresa): int => (int) $empresa['id'],
            $pagina->itens
        ));

        $porCriacao = $this->empresas->listar(
            $this->escritorioA,
            ['ordem' => 'criado_em'],
            $this->paginacao(1, 20)
        );
        self::assertSame($ids['Empresa B'], (int) $porCriacao->itens[0]['id']);
    }

    private function paginacao(int $pagina, int $porPagina): Paginacao
    {
        return Paginacao::deRequest(new Request('GET', '/empresas', ['pagina' => $pagina]), $porPagina);
    }

    /**
     * @param array<string, mixed> $opcoes
     * @return array<string, mixed>
     */
    private function dadosEmpresa(
        string $razaoSocial,
        ?string $cnpj = null,
        ?int $responsavelId = null,
        array $opcoes = []
    ): array {
        return array_merge([
            'razao_social' => $razaoSocial,
            'nome_fantasia' => null,
            'cnpj' => $cnpj ?? $this->novoCnpj(),
            'regime_tributario' => 'SIMPLES_NACIONAL',
            'segmento' => null,
            'contato_nome' => null,
            'email' => null,
            'telefone' => null,
            'responsavel_id' => $responsavelId,
            'bpo_ativo' => 0,
            'consultivo_ativo' => 0,
            'status' => 'ATIVA',
            'observacoes' => null,
        ], $opcoes);
    }

    private function novoCnpj(): string
    {
        return Cnpj::gerarFicticio($this->sementeCnpj++);
    }
}
