<?php

declare(strict_types=1);

namespace Tests\E2E;

use Tests\Support\ClienteHttp;
use Tests\Support\RespostaHttp;

/**
 * Avaliação de satisfação pelo HTTP: qualquer perfil avalia; só a equipe do escritório lê as avaliações.
 *
 * Roda só no banco tributo_test. Os comentários criados levam o prefixo "E2E-" e são apagados no tearDown.
 * Os testes "NaoUsa...Inline" descrevem o comportamento esperado depois da reconciliação com a ADR-024.
 */
final class AvaliacaoTest extends E2ETestCase
{
    private const SENHA = 'Senha@123';

    protected function tearDown(): void
    {
        $this->banco()->exec("DELETE FROM avaliacoes WHERE comentario LIKE 'E2E-%'");
        parent::tearDown();
    }

    public function testClienteAvaliaERedirecionaParaOPortal(): void
    {
        $cliente = $this->entrar('cliente.padaria@exemplo.test');
        $marca = $this->marca();

        $resposta = $this->enviar($cliente, ['nota' => '4', 'facilidade' => '5', 'comentario' => $marca]);

        self::assertSame(302, $resposta->status);
        self::assertSame('/portal', $resposta->location());
        self::assertSame(1, $this->contarComentario($marca));
    }

    public function testColaboradoraAvaliaEEquipeLeNaListagem(): void
    {
        $ana = $this->entrar('ana@exemplo.test');
        $marca = $this->marca();

        $resposta = $this->enviar($ana, ['nota' => '5', 'facilidade' => '', 'comentario' => $marca]);

        self::assertSame(302, $resposta->status);
        self::assertSame('/avaliacoes', $resposta->location());
        $lista = $ana->get('/avaliacoes');
        self::assertSame(200, $lista->status);
        self::assertStringContainsString($marca, $lista->corpo);
    }

    public function testNotaInvalidaMostraErroPorCampoENaoGrava(): void
    {
        $cliente = $this->entrar('cliente.padaria@exemplo.test');
        $marca = $this->marca();

        $vazia = $this->enviar($cliente, ['nota' => '', 'comentario' => $marca]);
        self::assertSame(422, $vazia->status);
        self::assertStringContainsString('Nota Geral é obrigatório.', $vazia->corpo);

        $fora = $this->enviar($cliente, ['nota' => '9', 'comentario' => $marca]);
        self::assertSame(422, $fora->status);
        self::assertStringContainsString('A nota geral deve estar entre 1 e 5 estrelas.', $fora->corpo);

        self::assertSame(0, $this->contarComentario($marca));
    }

    public function testComentarioComHtmlEhEscapadoNaListagem(): void
    {
        $cliente = $this->entrar('cliente.padaria@exemplo.test');
        $marca = $this->marca();
        $this->enviar($cliente, ['nota' => '3', 'comentario' => $marca . ' <script>alert(1)</script>']);

        $lista = $this->entrar('admin@exemplo.test')->get('/avaliacoes');

        self::assertStringContainsString($marca . ' &lt;script&gt;alert(1)&lt;/script&gt;', $lista->corpo);
        self::assertStringNotContainsString('<script>alert(1)</script>', $lista->corpo);
    }

    public function testClienteNaoLeAsAvaliacoesDoEscritorio(): void
    {
        $cliente = $this->entrar('cliente.padaria@exemplo.test');

        self::assertSame(403, $cliente->get('/avaliacoes')->status);
        self::assertSame(200, $cliente->get('/avaliacao')->status);
    }

    public function testEscritorioNaoVeAvaliacoesDeOutroEscritorio(): void
    {
        $marca = $this->marca();
        $this->enviar($this->entrar('cliente@modelo.test'), ['nota' => '2', 'comentario' => $marca]);

        $lista = $this->entrar('admin@exemplo.test')->get('/avaliacoes');

        self::assertSame(200, $lista->status);
        self::assertStringNotContainsString($marca, $lista->corpo);
    }

    public function testPostSemTokenCsrfRetorna419(): void
    {
        $cliente = $this->entrar('cliente.padaria@exemplo.test');

        self::assertSame(419, $cliente->post('/avaliacao', ['nota' => '5'])->status);
    }

    // ---- Esperado depois da reconciliação com a ADR-024 (CSP: sem style= nem handlers em linha) ----

    public function testListagemEFormularioDeAvaliacaoNaoUsamEstiloNemHandlerInline(): void
    {
        $marca = $this->marca();
        $cliente = $this->entrar('cliente.padaria@exemplo.test');
        $this->enviar($cliente, ['nota' => '5', 'facilidade' => '4', 'comentario' => $marca]);
        $admin = $this->entrar('admin@exemplo.test');

        $telas = [
            '/avaliacoes com avaliações' => $admin->get('/avaliacoes'),
            '/avaliacao (formulário)' => $cliente->get('/avaliacao'),
            '/avaliacao com erro (422)' => $this->enviar($cliente, ['nota' => '', 'comentario' => $marca]),
        ];

        foreach ($telas as $nome => $resposta) {
            self::assertDoesNotMatchRegularExpression('/\sstyle\s*=/i', $resposta->corpo, 'style= bloqueado pela CSP em ' . $nome);
            self::assertDoesNotMatchRegularExpression('/\son[a-z]+\s*=/i', $resposta->corpo, 'handler inline em ' . $nome);
        }
    }

    // ---- Apoio ----

    private function entrar(string $email): ClienteHttp
    {
        $cliente = $this->cliente();
        $token = ClienteHttp::extrairCsrf($cliente->get('/login')->corpo);
        self::assertNotNull($token);
        $resposta = $cliente->post('/login', ['_csrf' => $token, 'email' => $email, 'senha' => self::SENHA]);
        self::assertSame(302, $resposta->status);

        return $cliente;
    }

    /**
     * @param array<string, string> $campos
     */
    private function enviar(ClienteHttp $cliente, array $campos): RespostaHttp
    {
        $pagina = $cliente->get('/avaliacao');
        self::assertSame(200, $pagina->status);
        $token = ClienteHttp::extrairCsrf($pagina->corpo);
        self::assertNotNull($token);
        $campos['_csrf'] = $token;

        return $cliente->post('/avaliacao', $campos);
    }

    private function marca(): string
    {
        return 'E2E-' . bin2hex(random_bytes(4));
    }

    private function contarComentario(string $marca): int
    {
        $consulta = $this->banco()->prepare('SELECT COUNT(*) FROM avaliacoes WHERE comentario LIKE :marca');
        $consulta->execute(['marca' => $marca . '%']);

        return (int) $consulta->fetchColumn();
    }
}
