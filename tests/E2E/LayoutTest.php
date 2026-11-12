<?php

declare(strict_types=1);

namespace Tests\E2E;

use App\Core\View;
use Tests\Support\ClienteHttp;

require_once dirname(__DIR__, 2) . '/app/Core/helpers.php';

final class LayoutTest extends E2ETestCase
{
    public function testAdminDoModeloAssessoriaVeLayoutDoEscritorioSemItensContrataveis(): void
    {
        $resposta = $this->entrar('admin@modelo.test')->get(
            '/_teste/layout-escritorio?perfil=CLIENTE&modulos[]=BPO_FINANCEIRO'
        );

        self::assertSame(200, $resposta->status);
        self::assertStringStartsWith('<!doctype html>', $resposta->corpo);
        self::assertStringContainsString('aria-label="Navegação principal"', $resposta->corpo);
        self::assertStringContainsString('Usuários', $resposta->corpo);
        self::assertStringNotContainsString('BPO Financeiro', $resposta->corpo);
        self::assertStringNotContainsString('Consultivo', $resposta->corpo);
        self::assertStringNotContainsString('perfil=CLIENTE', $resposta->corpo);
    }

    public function testColaboradorVeMenuDoEscritorioSemOpcoesDeAdministrador(): void
    {
        $resposta = $this->entrar('ana@exemplo.test')->get('/_teste/layout-escritorio');

        self::assertSame(200, $resposta->status);
        self::assertStringContainsString('Painel', $resposta->corpo);
        self::assertStringContainsString('Empresas', $resposta->corpo);
        self::assertStringNotContainsString('Usuários', $resposta->corpo);
    }

    public function testClienteVeLayoutDoPortalSemMenuDoEscritorio(): void
    {
        $resposta = $this->entrar('cliente.padaria@exemplo.test')->get('/_teste/layout-portal');

        self::assertSame(200, $resposta->status);
        self::assertStringContainsString('Portal do cliente', $resposta->corpo);
        self::assertStringStartsWith('<!doctype html>', $resposta->corpo);
        self::assertStringContainsString('aria-label="Navegação principal"', $resposta->corpo);
        self::assertStringNotContainsString('Empresas', $resposta->corpo);
        self::assertStringNotContainsString('Usuários', $resposta->corpo);
    }

    public function testRotasDeLayoutRestringemOsPerfis(): void
    {
        $cliente = $this->entrar('cliente.padaria@exemplo.test');
        $colaborador = $this->entrar('ana@exemplo.test');

        self::assertSame(403, $cliente->get('/_teste/layout-escritorio')->status);
        self::assertSame(403, $colaborador->get('/_teste/layout-portal')->status);
    }

    public function testPartialsSemDadosOpcionaisNaoExibemBlocosVazios(): void
    {
        $view = new View();
        $marcadoresParciais = [
            'partials/abas-empresa' => ['dados' => [], 'marcador' => 'class="abas-empresa"'],
            'partials/campo-erro' => ['dados' => [], 'marcador' => 'class="erro-campo"'],
            'partials/semaforo' => ['dados' => [], 'marcador' => 'class="semaforo '],
            'partials/aviso-gerencial' => ['dados' => [], 'marcador' => 'class="aviso-gerencial"'],
            'partials/filtros-ativos' => ['dados' => [], 'marcador' => 'class="filtros-ativos"'],
            'partials/flash' => ['dados' => ['mensagens' => []], 'marcador' => 'class="mensagens-flash"'],
        ];

        foreach ($marcadoresParciais as $template => $teste) {
            $html = $view->render($template, $teste['dados'], 'impressao');

            self::assertStringNotContainsString($teste['marcador'], $html);
        }

        $paginacao = $view->render('partials/paginacao', [], 'impressao');
        self::assertStringNotContainsString('aria-label="Paginação"', $paginacao);
    }

    public function testPaginacaoPreservaOsFiltrosNaQueryString(): void
    {
        $html = (new View())->render('partials/paginacao', [
            'pagina' => 2,
            'totalPaginas' => 4,
            'caminho' => '/empresas',
            'queryAtual' => ['situacao' => 'aberta', 'pagina' => 2],
        ], 'impressao');

        $anterior = e(url('/empresas', ['situacao' => 'aberta', 'pagina' => 1]));
        $proxima = e(url('/empresas', ['situacao' => 'aberta', 'pagina' => 3]));
        self::assertStringContainsString('href="' . $anterior . '"', $html);
        self::assertStringContainsString('href="' . $proxima . '"', $html);
    }

    private function entrar(string $email): ClienteHttp
    {
        $cliente = $this->cliente();
        $login = $cliente->get('/login');
        $token = ClienteHttp::extrairCsrf($login->corpo);
        self::assertNotNull($token);

        $resposta = $cliente->post('/login', [
            '_csrf' => $token,
            'email' => $email,
            'senha' => 'Senha@123',
        ]);
        self::assertSame(302, $resposta->status);

        return $cliente;
    }
}
