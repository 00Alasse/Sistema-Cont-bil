<?php

declare(strict_types=1);

namespace Tests\E2E;

use Tests\Support\ClienteHttp;

final class LoginTest extends E2ETestCase
{
    private const SENHA = 'Senha@123';
    private const COOKIE = 'tributo_sessao';

    public function testLoginDeColaboradorRedirecionaParaOPainelEAbreAreaProtegida(): void
    {
        $cliente = $this->cliente();

        $resposta = $this->entrar($cliente, 'ana@exemplo.test');

        self::assertSame(302, $resposta->status);
        self::assertSame('/painel', $resposta->location());
        $protegida = $cliente->get('/_teste/protegida');
        self::assertSame(200, $protegida->status);
        self::assertStringContainsString('Área protegida de Ana Colaboradora (COLABORADOR)', $protegida->corpo);
    }

    public function testLoginDeClienteRedirecionaParaOPortal(): void
    {
        $resposta = $this->entrar($this->cliente(), 'cliente.padaria@exemplo.test');

        self::assertSame(302, $resposta->status);
        self::assertSame('/portal', $resposta->location());
    }

    public function testCredencialInvalidaDaMensagemGenericaEPreservaOEmail(): void
    {
        $cliente = $this->cliente();

        $senhaErrada = $this->entrar($cliente, 'ana@exemplo.test', 'senha-errada');
        $emailInexistente = $this->entrar($this->cliente(), 'ninguem@exemplo.test', 'senha-errada');

        foreach ([$senhaErrada, $emailInexistente] as $resposta) {
            self::assertSame(422, $resposta->status);
            self::assertStringContainsString('E-mail ou senha inválidos', $resposta->corpo);
        }
        self::assertStringContainsString('value="ana@exemplo.test"', $senhaErrada->corpo);
        self::assertStringNotContainsString('senha-errada', $senhaErrada->corpo);
    }

    public function testContaDesativadaTemAMesmaMensagemGenerica(): void
    {
        $this->banco()->exec("UPDATE usuarios SET ativo = 0 WHERE email = 'bruno@exemplo.test'");

        try {
            $resposta = $this->entrar($this->cliente(), 'bruno@exemplo.test');
        } finally {
            $this->banco()->exec("UPDATE usuarios SET ativo = 1 WHERE email = 'bruno@exemplo.test'");
        }

        self::assertSame(422, $resposta->status);
        self::assertStringContainsString('E-mail ou senha inválidos', $resposta->corpo);
    }

    public function testCamposVaziosOuInvalidosRetornamErrosPorCampo(): void
    {
        $cliente = $this->cliente();
        $token = $this->tokenDoLogin($cliente);

        $resposta = $cliente->post('/login', ['_csrf' => $token, 'email' => 'invalido', 'senha' => '']);

        self::assertSame(422, $resposta->status);
        self::assertStringContainsString('erro-campo', $resposta->corpo);
        self::assertStringContainsString('value="invalido"', $resposta->corpo);
    }

    public function testPostSemTokenCsrfRetorna419(): void
    {
        $resposta = $this->cliente()->post('/login', ['email' => 'ana@exemplo.test', 'senha' => self::SENHA]);

        self::assertSame(419, $resposta->status);
    }

    public function testLogoutEncerraASessaoEInvalidaOIdAntigo(): void
    {
        $cliente = $this->cliente();
        $this->entrar($cliente, 'ana@exemplo.test');
        $idLogado = (string) $cliente->cookie(self::COOKIE);
        $token = ClienteHttp::extrairCsrf($cliente->get('/_teste/protegida')->corpo);
        self::assertNotNull($token);

        $saida = $cliente->post('/logout', ['_csrf' => $token]);

        self::assertSame(302, $saida->status);
        self::assertSame('/login', $saida->location());
        self::assertSame(302, $cliente->get('/_teste/protegida')->status);
        $reaproveitado = $this->cliente()->comCookie(self::COOKIE, $idLogado)->get('/_teste/protegida');
        self::assertSame(302, $reaproveitado->status);
        self::assertSame('/login', $reaproveitado->location());
    }

    public function testAcessoSemSessaoARotaProtegidaVaiParaOLogin(): void
    {
        $resposta = $this->cliente()->get('/_teste/protegida');

        self::assertSame(302, $resposta->status);
        self::assertSame('/login', $resposta->location());
    }

    public function testDepoisDoLoginVoltaParaAUrlOriginalmenteSolicitada(): void
    {
        $cliente = $this->cliente();
        $cliente->get('/_teste/protegida?aba=1');

        $resposta = $this->entrar($cliente, 'ana@exemplo.test');

        self::assertSame('/_teste/protegida?aba=1', $resposta->location());
    }

    public function testLoginRegeneraOIdDaSessaoETrocaOTokenCsrf(): void
    {
        $cliente = $this->cliente();
        $tokenAntes = $this->tokenDoLogin($cliente);
        $idAntes = $cliente->cookie(self::COOKIE);
        self::assertNotNull($idAntes);

        $cliente->post('/login', ['_csrf' => $tokenAntes, 'email' => 'ana@exemplo.test', 'senha' => self::SENHA]);

        $idDepois = $cliente->cookie(self::COOKIE);
        self::assertNotNull($idDepois);
        self::assertNotSame($idAntes, $idDepois);
        $tokenDepois = ClienteHttp::extrairCsrf($cliente->get('/_teste/protegida')->corpo);
        self::assertNotNull($tokenDepois);
        self::assertNotSame($tokenAntes, $tokenDepois);
        // O ID antigo não dá acesso a nada.
        self::assertSame(302, $this->cliente()->comCookie(self::COOKIE, $idAntes)->get('/_teste/protegida')->status);
    }

    public function testUsuarioJaAutenticadoNaoVeOFormularioDeLogin(): void
    {
        $cliente = $this->cliente();
        $this->entrar($cliente, 'admin@exemplo.test');

        $resposta = $cliente->get('/login');

        self::assertSame(302, $resposta->status);
        self::assertSame('/painel', $resposta->location());
    }

    public function testUsuarioDesativadoPerdeOAcessoNaProximaRequisicao(): void
    {
        $cliente = $this->cliente();
        $this->entrar($cliente, 'bruno@exemplo.test');
        self::assertSame(200, $cliente->get('/_teste/protegida')->status);

        $this->banco()->exec("UPDATE usuarios SET ativo = 0 WHERE email = 'bruno@exemplo.test'");
        try {
            $resposta = $cliente->get('/_teste/protegida');
            $login = $cliente->get('/login');
        } finally {
            $this->banco()->exec("UPDATE usuarios SET ativo = 1 WHERE email = 'bruno@exemplo.test'");
        }

        self::assertSame(302, $resposta->status);
        self::assertSame('/login', $resposta->location());
        self::assertSame(200, $login->status);
    }

    public function testBloqueiaAposCincoFalhasMesmoComSenhaCorreta(): void
    {
        $cliente = $this->cliente();

        for ($i = 0; $i < 5; $i++) {
            self::assertSame(422, $this->entrar($cliente, 'ana@exemplo.test', 'errada-' . $i)->status);
        }
        $bloqueada = $this->entrar($cliente, 'ana@exemplo.test');

        self::assertSame(429, $bloqueada->status);
        self::assertStringContainsString('Muitas tentativas. Tente novamente em alguns minutos.', $bloqueada->corpo);
        self::assertSame(302, $cliente->get('/_teste/protegida')->status);
    }

    public function testRespostasTrazemOsHeadersDeSeguranca(): void
    {
        $resposta = $this->cliente()->get('/login');

        self::assertSame('DENY', $resposta->header('X-Frame-Options'));
        self::assertStringContainsString("default-src 'self'", (string) $resposta->header('Content-Security-Policy'));
    }

    public function testCookieDeSessaoEHttpOnlyESameSiteLax(): void
    {
        $setCookie = implode(' | ', $this->cliente()->get('/login')->headers['set-cookie'] ?? []);

        self::assertStringContainsString(self::COOKIE . '=', $setCookie);
        self::assertStringContainsString('HttpOnly', $setCookie);
        self::assertStringContainsString('SameSite=Lax', $setCookie);
    }

    private function tokenDoLogin(ClienteHttp $cliente): string
    {
        $pagina = $cliente->get('/login');
        self::assertSame(200, $pagina->status);
        $token = ClienteHttp::extrairCsrf($pagina->corpo);
        self::assertNotNull($token);

        return $token;
    }

    private function entrar(
        ClienteHttp $cliente,
        string $email,
        string $senha = self::SENHA
    ): \Tests\Support\RespostaHttp {
        return $cliente->post('/login', [
            '_csrf' => $this->tokenDoLogin($cliente),
            'email' => $email,
            'senha' => $senha,
        ]);
    }
}
