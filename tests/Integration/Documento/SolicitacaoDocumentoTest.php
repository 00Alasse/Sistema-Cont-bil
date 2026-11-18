<?php

declare(strict_types=1);

namespace Tests\Integration\Documento;

use App\Core\Paginacao;
use App\Core\Request;
use App\Models\Documento\SolicitacaoDocumento;
use App\Support\Cnpj;
use Tests\Integration\IntegrationTestCase;
use Tests\Support\FabricaCadastro;

final class SolicitacaoDocumentoTest extends IntegrationTestCase
{
    private FabricaCadastro $fabrica;
    private SolicitacaoDocumento $solicitacoes;
    private int $escritorioA;
    private int $escritorioB;
    private int $empresaA;
    private int $empresaB;
    private int $usuarioA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fabrica = new FabricaCadastro($this->conexao());
        $this->solicitacoes = new SolicitacaoDocumento();

        $this->escritorioA = $this->fabrica->criarEscritorio();
        $this->escritorioB = $this->fabrica->criarEscritorio();
        $this->usuarioA = $this->fabrica->criarUsuario($this->escritorioA, 'ADMIN');

        $this->empresaA = $this->fabrica->criarEmpresa($this->escritorioA, Cnpj::gerarFicticio(1111), 'Empresa Teste A');
        $this->empresaB = $this->fabrica->criarEmpresa($this->escritorioB, Cnpj::gerarFicticio(2222), 'Empresa Teste B');
    }

    public function testCriaEBuscaSolicitacaoComIsolamentoPorEscritorio(): void
    {
        $id = $this->solicitacoes->criar($this->escritorioA, [
            'empresa_id' => $this->empresaA,
            'titulo' => 'Extrato Bancário',
            'descricao' => 'Enviar PDF',
            'prazo' => date('Y-m-d', strtotime('+5 days')),
            'criado_por' => $this->usuarioA,
        ]);

        $itemA = $this->solicitacoes->buscar($this->escritorioA, $id);
        $this->assertNotNull($itemA);
        $this->assertSame('Extrato Bancário', $itemA['titulo']);
        $this->assertSame('aberta', $itemA['status']);

        // Outro escritório NUNCA enxerga
        $itemB = $this->solicitacoes->buscar($this->escritorioB, $id);
        $this->assertNull($itemB);
    }

    public function testFluxoCompletoDeEnvioAceiteERecusa(): void
    {
        $id = $this->solicitacoes->criar($this->escritorioA, [
            'empresa_id' => $this->empresaA,
            'titulo' => 'Notas de Compra',
            'descricao' => 'XMLs do mês',
            'prazo' => date('Y-m-d', strtotime('+3 days')),
            'criado_por' => $this->usuarioA,
        ]);

        // Empresário anexa arquivo
        $this->solicitacoes->anexarArquivo(
            $this->escritorioA,
            $id,
            'arquivo_hash.xml',
            'Notas_Outubro.xml',
            date('Y-m-d H:i:s')
        );

        $enviada = $this->solicitacoes->buscar($this->escritorioA, $id);
        $this->assertNotNull($enviada);
        $this->assertSame('enviada', $enviada['status']);
        $this->assertSame('arquivo_hash.xml', $enviada['arquivo_path']);

        // Contador recusa com motivo
        $this->solicitacoes->recusar($this->escritorioA, $id, 'Falta a nota 1234');
        $recusada = $this->solicitacoes->buscar($this->escritorioA, $id);
        $this->assertNotNull($recusada);
        $this->assertSame('recusada', $recusada['status']);
        $this->assertSame('Falta a nota 1234', $recusada['motivo_recusa']);

        // Contador aceita
        $this->solicitacoes->aceitar($this->escritorioA, $id);
        $aceita = $this->solicitacoes->buscar($this->escritorioA, $id);
        $this->assertNotNull($aceita);
        $this->assertSame('aceita', $aceita['status']);
        $this->assertNull($aceita['motivo_recusa']);
    }

    public function testBuscaPorTextoProcuraNoTituloRazaoSocialENomeFantasia(): void
    {
        // Regressão: :busca repetido três vezes gerava HY093 com prepares nativos.
        $fantasia = $this->fabrica->criarEmpresa(
            $this->escritorioA,
            Cnpj::gerarFicticio(3333),
            'Comércio Sem Termo Ltda',
            ['nome_fantasia' => 'Padaria Girassol']
        );
        $porTitulo = $this->novaSolicitacao($this->empresaA, 'Extrato Girassol');
        $porFantasia = $this->novaSolicitacao($fantasia, 'Balancete anual');
        $porRazao = $this->novaSolicitacao($this->empresaA, 'Contrato social');
        $this->novaSolicitacao($this->empresaA, 'Folha de pagamento');
        $this->solicitacoes->criar($this->escritorioB, [
            'empresa_id' => $this->empresaB, 'titulo' => 'Girassol de outro escritório',
            'prazo' => date('Y-m-d'), 'criado_por' => $this->fabrica->criarUsuario($this->escritorioB),
        ]);

        $girassol = $this->solicitacoes->listar($this->escritorioA, ['busca' => 'Girassol'], $this->paginacao());
        $ids = array_map(static fn (array $item): int => (int) $item['id'], $girassol->itens);
        sort($ids);
        $esperados = [$porTitulo, $porFantasia];
        sort($esperados);
        $this->assertSame($esperados, $ids);
        $this->assertSame(2, $girassol->total);

        $porRazaoSocial = $this->solicitacoes->listar($this->escritorioA, ['busca' => 'Empresa Teste A'], $this->paginacao());
        $this->assertSame(3, $porRazaoSocial->total);
        $this->assertContains($porRazao, array_map(static fn (array $item): int => (int) $item['id'], $porRazaoSocial->itens));

        // Curingas do LIKE digitados pelo usuário são literais.
        $this->assertSame(0, $this->solicitacoes->listar($this->escritorioA, ['busca' => '%'], $this->paginacao())->total);
    }

    private function novaSolicitacao(int $empresaId, string $titulo): int
    {
        return $this->solicitacoes->criar($this->escritorioA, [
            'empresa_id' => $empresaId, 'titulo' => $titulo, 'prazo' => date('Y-m-d'), 'criado_por' => $this->usuarioA,
        ]);
    }

    private function paginacao(): Paginacao
    {
        return Paginacao::deRequest(new Request('GET', '/solicitacoes', ['pagina' => 1]), 15);
    }

    public function testListarPorEmpresaFiltraApenasPropriaEmpresa(): void
    {
        $this->solicitacoes->criar($this->escritorioA, [
            'empresa_id' => $this->empresaA,
            'titulo' => 'Solicitação Empresa A',
            'prazo' => date('Y-m-d'),
            'criado_por' => $this->usuarioA,
        ]);

        $docsA = $this->solicitacoes->listarPorEmpresa($this->escritorioA, $this->empresaA);
        $this->assertCount(1, $docsA);

        $docsB = $this->solicitacoes->listarPorEmpresa($this->escritorioA, $this->empresaB);
        $this->assertCount(0, $docsB);
    }
}
