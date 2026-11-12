<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Menu;
use App\Core\UsuarioLogado;
use PHPUnit\Framework\TestCase;

final class MenuTest extends TestCase
{
    public function testFiltraItensPeloPerfilEOrdenaPeloNumeroDeExibicao(): void
    {
        $menu = new Menu($this->modulos());
        $usuario = $this->usuario('COLABORADOR', ['BPO_FINANCEIRO']);

        $itens = $menu->itens($usuario);

        self::assertSame(
            ['Painel', 'Financeiro', 'Empresas'],
            array_column($itens, 'rotulo')
        );
    }

    public function testOcultaItensDosModulosNaoAtivosNoEscritorio(): void
    {
        $menu = new Menu($this->modulos());
        $usuario = $this->usuario('ADMIN', []);

        $itens = $menu->itens($usuario);

        self::assertSame(['Painel', 'Empresas', 'Usuários'], array_column($itens, 'rotulo'));
        self::assertNotContains('Financeiro', array_column($itens, 'rotulo'));
        self::assertNotContains('Consultivo', array_column($itens, 'rotulo'));
    }

    public function testIncluiSomenteItensDosModulosAtivosEOrdenaTodosEmConjunto(): void
    {
        $menu = new Menu($this->modulos());
        $usuario = $this->usuario('ADMIN', ['BPO_FINANCEIRO', 'CONSULTIVO']);

        $itens = $menu->itens($usuario);

        self::assertSame(
            ['Painel', 'Financeiro', 'Consultivo', 'Empresas', 'Usuários'],
            array_column($itens, 'rotulo')
        );
    }

    public function testMenuDoClienteSoContemItensPermitidosAoPerfil(): void
    {
        $menu = new Menu($this->modulos());
        $usuario = $this->usuario('CLIENTE', []);

        $itens = $menu->itens($usuario);

        self::assertSame(['Portal do cliente'], array_column($itens, 'rotulo'));
    }

    /**
     * @param list<string> $modulos
     */
    private function usuario(string $perfil, array $modulos): UsuarioLogado
    {
        return new UsuarioLogado(1, 1, $perfil, null, 'Pessoa de teste', $modulos);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function modulos(): array
    {
        return [
            'nucleo' => [
                'contratavel' => null,
                'menu' => [
                    ['rotulo' => 'Empresas', 'url' => '/empresas', 'icone' => 'empresa',
                        'perfis' => ['ADMIN', 'COLABORADOR'], 'ordem' => 40],
                    ['rotulo' => 'Usuários', 'url' => '/usuarios', 'icone' => 'usuarios',
                        'perfis' => ['ADMIN'], 'ordem' => 50],
                    ['rotulo' => 'Painel', 'url' => '/painel', 'icone' => 'painel',
                        'perfis' => ['ADMIN', 'COLABORADOR'], 'ordem' => 10],
                    ['rotulo' => 'Portal do cliente', 'url' => '/portal', 'icone' => 'portal',
                        'perfis' => ['CLIENTE'], 'ordem' => 10],
                ],
            ],
            'financeiro' => [
                'contratavel' => 'BPO_FINANCEIRO',
                'menu' => [
                    ['rotulo' => 'Financeiro', 'url' => '/financeiro', 'icone' => 'financeiro',
                        'perfis' => ['ADMIN', 'COLABORADOR'], 'ordem' => 20],
                ],
            ],
            'consultivo' => [
                'contratavel' => 'CONSULTIVO',
                'menu' => [
                    ['rotulo' => 'Consultivo', 'url' => '/consultivo', 'icone' => 'consultivo',
                        'perfis' => ['ADMIN', 'COLABORADOR'], 'ordem' => 30],
                ],
            ],
        ];
    }
}
