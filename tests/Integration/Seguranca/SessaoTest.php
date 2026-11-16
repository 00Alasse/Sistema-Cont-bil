<?php

declare(strict_types=1);

namespace Tests\Integration\Seguranca;

use Tests\E2E\E2ETestCase;
use Tests\Support\ClienteHttp;
use Tests\Support\FabricaCadastro;
use Tests\Support\RespostaHttp;

/**
 * Sessão e senhas (ARCHITECTURE §8 e §11.1), pelo HTTP.
 *
 * Sessão: cookie HttpOnly e SameSite=Lax, modo estrito (ID inventado é recusado), ID novo no
 * login, logout só por POST com CSRF e com a sessão destruída. A expiração por inatividade usa
 * o relógio e já é provada em tests/Unit/Core/SessionTest.php.
 *
 * Senhas: guardadas só como hash (password_hash), atualizadas quando o custo muda
 * (password_needs_rehash) e nunca devolvidas nem gravadas em texto puro.
 */
final class SessaoTest extends E2ETestCase
{
    private const SENHA = 'Senha@123';

    private const COOKIE = 'tributo_sessao';

    public function testCookieDeSessaoEHttpOnlySameSiteLaxNoCaminhoRaiz(): void
    {
        $cliente = $this->cliente();
        $visita = $cliente->get('/login');
        $login = $this->postarLogin($cliente, $this->novoUsuario()['email'], self::SENHA, $visita);

        foreach (['visita' => $visita, 'login' => $login] as $momento => $resposta) {
            $cookie = $this->setCookieDaSessao($resposta);
            self::assertNotNull($cookie, 'A resposta do ' . $momento . ' devia (re)definir o cookie de sessão.');
            self::assertMatchesRegularExpression('/;\s*HttpOnly(;|$)/i', $cookie, $momento);
            self::assertMatchesRegularExpression('/;\s*SameSite=Lax(;|$)/i', $cookie, $momento);
            self::assertMatchesRegularExpression('/;\s*path=\/(;|$)/i', $cookie, $momento);
        }
    }

    public function testIdDeSessaoInventadoPeloVisitanteNaoEAceito(): void
    {
        $forjado = str_repeat('a', 32);

        $resposta = $this->cliente()->comCookie(self::COOKIE, $forjado)->get('/login');

        // Modo estrito: o servidor recusa o ID que não criou e emite outro.
        $cookie = $this->setCookieDaSessao($resposta);
        self::assertNotNull($cookie);
        self::assertStringNotContainsString(self::COOKIE . '=' . $forjado, $cookie);
        self::assertSame(302, $this->cliente()->comCookie(self::COOKIE, $forjado)->get('/_teste/protegida')->status);
    }

    public function testLoginTrocaOIdDaSessaoEOIdAntigoNaoServeMais(): void
    {
        $cliente = $this->cliente();
        $visita = $cliente->get('/login');
        $idAntes = $cliente->cookie(self::COOKIE);
        self::assertNotNull($idAntes);

        $this->postarLogin($cliente, $this->novoUsuario()['email'], self::SENHA, $visita);

        $idDepois = $cliente->cookie(self::COOKIE);
        self::assertNotNull($idDepois);
        self::assertNotSame($idAntes, $idDepois);
        self::assertSame(200, $cliente->get('/_teste/protegida')->status);
        // Fixação de sessão: quem conhecia o ID anterior ao login não ganha o acesso.
        self::assertSame(302, $this->cliente()->comCookie(self::COOKIE, $idAntes)->get('/_teste/protegida')->status);
    }

    public function testLogoutDestroiASessaoEInvalidaOId(): void
    {
        $cliente = $this->entrar($this->novoUsuario()['email']);
        $idLogado = (string) $cliente->cookie(self::COOKIE);

        $saida = $cliente->post('/logout', ['_csrf' => $this->token($cliente)]);

        self::assertSame(302, $saida->status);
        self::assertSame('/login', $saida->location());
        self::assertSame(302, $cliente->get('/_teste/protegida')->status);
        $reaproveitado = $this->cliente()->comCookie(self::COOKIE, $idLogado)->get('/_teste/protegida');
        self::assertSame(302, $reaproveitado->status);
        self::assertSame('/login', $reaproveitado->location());
    }

    public function testLogoutSoAconteceComPostEToken(): void
    {
        $cliente = $this->entrar($this->novoUsuario()['email']);

        self::assertSame(405, $cliente->get('/logout')->status);
        self::assertSame(419, $cliente->post('/logout', [])->status);
        // Um link ou formulário de outro site não encerra a sessão de ninguém.
        self::assertSame(200, $cliente->get('/_teste/protegida')->status);
    }

    public function testSenhaEGuardadaSoComoHashEConfereNoLogin(): void
    {
        $usuario = $this->novoUsuario();
        $this->entrar($usuario['email']);

        $hash = $this->hashDe($usuario['id']);
        self::assertNotSame(self::SENHA, $hash);
        self::assertStringNotContainsString(self::SENHA, $hash);
        self::assertNotSame('unknown', password_get_info($hash)['algoName']);
        self::assertTrue(password_verify(self::SENHA, $hash));

        // Nenhuma senha cadastrada (seeds e testes) está em texto puro.
        $hashes = $this->banco()->query('SELECT senha_hash FROM usuarios')->fetchAll(\PDO::FETCH_COLUMN);
        self::assertNotEmpty($hashes);
        foreach ($hashes as $guardado) {
            self::assertNotSame('unknown', password_get_info((string) $guardado)['algoName']);
        }
    }

    public function testHashComCustoAntigoEAtualizadoNoLogin(): void
    {
        $usuario = $this->novoUsuario();
        $antigo = password_hash(self::SENHA, PASSWORD_BCRYPT, ['cost' => 4]);
        $this->banco()->prepare('UPDATE usuarios SET senha_hash = :hash WHERE id = :id')
            ->execute(['hash' => $antigo, 'id' => $usuario['id']]);

        $this->entrar($usuario['email']);

        $novo = $this->hashDe($usuario['id']);
        self::assertNotSame($antigo, $novo);
        self::assertFalse(password_needs_rehash($novo, PASSWORD_DEFAULT));
        self::assertTrue(password_verify(self::SENHA, $novo));
    }

    public function testSenhaDigitadaNaoVoltaNaRespostaNemFicaGravada(): void
    {
        $usuario = $this->novoUsuario();
        $senhaErrada = 'SenhaErradaQ01' . bin2hex(random_bytes(4));
        $cliente = $this->cliente();

        $resposta = $this->postarLogin($cliente, $usuario['email'], $senhaErrada, $cliente->get('/login'));

        self::assertSame(422, $resposta->status);
        self::assertStringNotContainsString($senhaErrada, $resposta->corpo);
        foreach (['tentativas_login', 'logs_atividade'] as $tabela) {
            $linhas = $this->banco()->query("SELECT * FROM {$tabela}")->fetchAll();
            self::assertStringNotContainsString($senhaErrada, (string) json_encode($linhas), $tabela);
        }
        foreach (glob(dirname(__DIR__, 3) . '/storage/logs/*.log') ?: [] as $log) {
            self::assertStringNotContainsString($senhaErrada, (string) file_get_contents($log), basename($log));
        }
    }

    /**
     * @return array{id: int, email: string}
     */
    private function novoUsuario(): array
    {
        $fabrica = new FabricaCadastro($this->banco());
        $id = $fabrica->criarUsuario($fabrica->criarEscritorio(), 'COLABORADOR');
        $stmt = $this->banco()->prepare('SELECT email FROM usuarios WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return ['id' => $id, 'email' => (string) $stmt->fetchColumn()];
    }

    private function hashDe(int $usuarioId): string
    {
        $stmt = $this->banco()->prepare('SELECT senha_hash FROM usuarios WHERE id = :id');
        $stmt->execute(['id' => $usuarioId]);

        return (string) $stmt->fetchColumn();
    }

    /**
     * Linha Set-Cookie do cookie de sessão, ou null se a resposta não o definiu.
     */
    private function setCookieDaSessao(RespostaHttp $resposta): ?string
    {
        foreach ($resposta->headers['set-cookie'] ?? [] as $linha) {
            if (str_starts_with($linha, self::COOKIE . '=')) {
                return $linha;
            }
        }

        return null;
    }

    private function postarLogin(ClienteHttp $cliente, string $email, string $senha, RespostaHttp $paginaDeLogin): RespostaHttp
    {
        $token = ClienteHttp::extrairCsrf($paginaDeLogin->corpo);
        self::assertNotNull($token);

        return $cliente->post('/login', ['_csrf' => $token, 'email' => $email, 'senha' => $senha]);
    }

    private function entrar(string $email): ClienteHttp
    {
        $cliente = $this->cliente();
        $resposta = $this->postarLogin($cliente, $email, self::SENHA, $cliente->get('/login'));
        self::assertSame(302, $resposta->status);

        return $cliente;
    }

    private function token(ClienteHttp $cliente): string
    {
        $token = ClienteHttp::extrairCsrf($cliente->get('/_teste/protegida')->corpo);
        self::assertNotNull($token);

        return $token;
    }
}
