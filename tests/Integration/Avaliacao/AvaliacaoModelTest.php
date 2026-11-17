<?php

declare(strict_types=1);

namespace Tests\Integration\Avaliacao;

use App\Core\Paginacao;
use App\Models\Avaliacao\Avaliacao;
use Tests\Integration\IntegrationTestCase;
use Tests\Support\FabricaCadastro;

final class AvaliacaoModelTest extends IntegrationTestCase
{
    private FabricaCadastro $fabrica;
    private Avaliacao $avaliacoes;
    private int $escritorioA;
    private int $escritorioB;
    private int $usuarioA;
    private int $usuarioB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fabrica = new FabricaCadastro($this->conexao());
        $this->avaliacoes = new Avaliacao();

        $this->escritorioA = $this->fabrica->criarEscritorio();
        $this->escritorioB = $this->fabrica->criarEscritorio();

        $this->usuarioA = $this->fabrica->criarUsuario($this->escritorioA, 'ADMIN');
        $this->usuarioB = $this->fabrica->criarUsuario($this->escritorioB, 'ADMIN');
    }

    public function testCriaListaECalculaEstatisticas(): void
    {
        $this->avaliacoes->criar($this->escritorioA, $this->usuarioA, 5, 5, 'Excelente plataforma!');
        $this->avaliacoes->criar($this->escritorioA, $this->usuarioA, 4, 3, 'Bom sistema, pode melhorar a busca.');

        // Escritório B
        $this->avaliacoes->criar($this->escritorioB, $this->usuarioB, 1, 1, 'Avaliação de outro escritório');

        $statsA = $this->avaliacoes->estatisticas($this->escritorioA);
        $this->assertSame(2, $statsA['total']);
        $this->assertEquals(4.5, $statsA['media_nota']);
        $this->assertEquals(4.0, $statsA['media_facilidade']);

        $paginacao = Paginacao::deRequest(new \App\Core\Request('GET', '/avaliacoes'), 10);
        $paginaA = $this->avaliacoes->listar($this->escritorioA, $paginacao);
        $this->assertCount(2, $paginaA->itens);
        $this->assertSame(2, $paginaA->total);

        // Isolamento de escritório: escritório B vê apenas suas próprias avaliações
        $paginaB = $this->avaliacoes->listar($this->escritorioB, $paginacao);
        $this->assertCount(1, $paginaB->itens);
        $this->assertSame('Avaliação de outro escritório', $paginaB->itens[0]['comentario']);
    }
}
