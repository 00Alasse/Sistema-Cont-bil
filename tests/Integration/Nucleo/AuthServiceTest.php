<?php

declare(strict_types=1);

namespace Tests\Integration\Nucleo;

use App\Core\Session;
use App\Models\Nucleo\TentativaLogin;
use App\Models\Nucleo\Usuario;
use App\Services\Nucleo\AuthService;
use App\Support\Relogio;
use App\Support\RelogioFixo;
use DateTimeImmutable;
use Tests\Integration\IntegrationTestCase;

final class AuthServiceTest extends IntegrationTestCase
{
    private const SENHA = 'Senha@123';
    private const IP = '203.0.113.10';

    public function testLoginBemSucedidoDevolveUsuarioLogadoComModulos(): void
    {
        $escritorio = $this->criarEscritorio();
        $this->ativarModulo($escritorio, 'BPO_FINANCEIRO');
        $usuarioId = $this->criarUsuario($escritorio, 'ana@exemplo.test', 'COLABORADOR');

        $usuario = $this->servico()->autenticar('  ANA@exemplo.test ', self::SENHA, self::IP);

        self::assertNotNull($usuario);
        self::assertSame($usuarioId, $usuario->id);
        self::assertSame($escritorio, $usuario->escritorioId);
        self::assertSame('COLABORADOR', $usuario->perfil);
        self::assertNull($usuario->empresaId);
        self::assertSame(['BPO_FINANCEIRO'], $usuario->modulos);
        self::assertTrue($usuario->temModulo('BPO_FINANCEIRO'));
        self::assertFalse($usuario->temModulo('CONSULTIVO'));
        self::assertFalse($usuario->ehAdmin());
        self::assertSame('2026-03-10 10:00:00', $this->coluna('SELECT ultimo_login_em FROM usuarios WHERE id = ' . $usuarioId));
        self::assertSame('1', $this->coluna('SELECT sucesso FROM tentativas_login WHERE email = \'ana@exemplo.test\''));
    }

    public function testSenhaIncorretaNaoAutenticaERegistraFalha(): void
    {
        $this->criarUsuario($this->criarEscritorio(), 'ana@exemplo.test');

        self::assertNull($this->servico()->autenticar('ana@exemplo.test', 'errada-123', self::IP));
        self::assertSame('0', $this->coluna('SELECT sucesso FROM tentativas_login WHERE email = \'ana@exemplo.test\''));
    }

    public function testEmailInexistenteNaoAutenticaERegistraFalha(): void
    {
        self::assertNull($this->servico()->autenticar('ninguem@exemplo.test', self::SENHA, self::IP));
        self::assertSame('1', $this->coluna('SELECT COUNT(*) FROM tentativas_login WHERE sucesso = 0'));
    }

    public function testUsuarioInativoNaoAutenticaMesmoComSenhaCorreta(): void
    {
        $this->criarUsuario($this->criarEscritorio(), 'inativo@exemplo.test', 'ADMIN', false);

        self::assertNull($this->servico()->autenticar('inativo@exemplo.test', self::SENHA, self::IP));
    }

    public function testEscritorioInativoNaoAutenticaMesmoComSenhaCorreta(): void
    {
        $this->criarUsuario($this->criarEscritorio(false), 'admin@exemplo.test');

        self::assertNull($this->servico()->autenticar('admin@exemplo.test', self::SENHA, self::IP));
    }

    public function testBloqueiaPorEmailAposCincoFalhasEDesbloqueiaDepoisDaJanela(): void
    {
        $this->criarUsuario($this->criarEscritorio(), 'ana@exemplo.test');
        $servico = $this->servico();

        for ($i = 0; $i < 5; $i++) {
            self::assertFalse($servico->bloqueado('ana@exemplo.test', '198.51.100.' . $i));
            $servico->autenticar('ana@exemplo.test', 'errada-123', '198.51.100.' . $i);
        }

        self::assertTrue($servico->bloqueado('ana@exemplo.test', '198.51.100.99'));
        // Bloqueado: nem a senha correta autentica.
        self::assertNull($servico->autenticar('ana@exemplo.test', self::SENHA, '198.51.100.99'));

        $depois = $this->servico(new RelogioFixo(new DateTimeImmutable('2026-03-10 10:16:00')));
        self::assertFalse($depois->bloqueado('ana@exemplo.test', '198.51.100.99'));
        self::assertNotNull($depois->autenticar('ana@exemplo.test', self::SENHA, '198.51.100.99'));
    }

    public function testBloqueiaPorIpAposVinteFalhasEmEmailsDiferentes(): void
    {
        $servico = $this->servico();

        for ($i = 0; $i < 20; $i++) {
            self::assertFalse($servico->bloqueado('alvo' . $i . '@exemplo.test', self::IP));
            $servico->autenticar('alvo' . $i . '@exemplo.test', 'errada-123', self::IP);
        }

        self::assertTrue($servico->bloqueado('outro@exemplo.test', self::IP));
        self::assertFalse($servico->bloqueado('outro@exemplo.test', '198.51.100.7'));
    }

    public function testLoginBemSucedidoZeraAContagemDoEmail(): void
    {
        $this->criarUsuario($this->criarEscritorio(), 'ana@exemplo.test');
        $servico = $this->servico();

        for ($i = 0; $i < 4; $i++) {
            $servico->autenticar('ana@exemplo.test', 'errada-123', self::IP);
        }
        self::assertNotNull($servico->autenticar('ana@exemplo.test', self::SENHA, self::IP));
        $servico->autenticar('ana@exemplo.test', 'errada-123', self::IP);

        self::assertFalse($servico->bloqueado('ana@exemplo.test', '198.51.100.1'));
    }

    public function testAtualizaOHashAntigoNoLogin(): void
    {
        $usuarioId = $this->criarUsuario(
            $this->criarEscritorio(),
            'ana@exemplo.test',
            'ADMIN',
            true,
            password_hash(self::SENHA, PASSWORD_BCRYPT, ['cost' => 4])
        );

        self::assertNotNull($this->servico()->autenticar('ana@exemplo.test', self::SENHA, self::IP));

        $hash = $this->coluna('SELECT senha_hash FROM usuarios WHERE id = ' . $usuarioId);
        self::assertFalse(password_needs_rehash($hash, PASSWORD_DEFAULT));
        self::assertTrue(password_verify(self::SENHA, $hash));
    }

    public function testNaoGravaSenhaNemHashNaTabelaDeTentativas(): void
    {
        $this->criarUsuario($this->criarEscritorio(), 'ana@exemplo.test');
        $this->servico()->autenticar('ana@exemplo.test', 'segredo-digitado', self::IP);

        $linhas = $this->conexao()->query('SELECT * FROM tentativas_login')->fetchAll();
        self::assertStringNotContainsString('segredo-digitado', (string) json_encode($linhas));
    }

    public function testUsuarioDaSessaoReleEstadoAtualEEncerraQuandoDesativado(): void
    {
        $escritorio = $this->criarEscritorio();
        $usuarioId = $this->criarUsuario($escritorio, 'ana@exemplo.test');
        $servico = $this->servico();
        $dados = [];
        $sessao = Session::emArray($dados, new RelogioFixo(new DateTimeImmutable('2026-03-10 10:00:00')), 30);
        $sessao->iniciar();

        $usuario = $servico->autenticar('ana@exemplo.test', self::SENHA, self::IP);
        self::assertNotNull($usuario);
        $servico->abrirSessao($sessao, $usuario);
        self::assertSame($usuarioId, $servico->usuarioDaSessao($sessao)?->id);

        $this->conexao()->exec('UPDATE usuarios SET ativo = 0 WHERE id = ' . $usuarioId);

        self::assertNull($servico->usuarioDaSessao($sessao));
        self::assertNull($sessao->obter('auth_usuario_id'));
    }

    public function testCarregarUsuarioNaoCruzaEscritorios(): void
    {
        $escritorioA = $this->criarEscritorio();
        $escritorioB = $this->criarEscritorio();
        $usuarioId = $this->criarUsuario($escritorioA, 'ana@exemplo.test');

        self::assertNotNull($this->servico()->carregarUsuario($escritorioA, $usuarioId));
        self::assertNull($this->servico()->carregarUsuario($escritorioB, $usuarioId));
    }

    public function testAbrirSessaoTrocaOIdEDescartaOTokenCsrfAntigo(): void
    {
        $escritorio = $this->criarEscritorio();
        $this->criarUsuario($escritorio, 'ana@exemplo.test');
        $dados = [];
        $sessao = Session::emArray($dados, new RelogioFixo(new DateTimeImmutable('2026-03-10 10:00:00')), 30);
        $sessao->iniciar();
        $sessao->definir('_csrf', 'token-antigo');
        $idAntes = $sessao->id();

        $usuario = $this->servico()->autenticar('ana@exemplo.test', self::SENHA, self::IP);
        self::assertNotNull($usuario);
        $this->servico()->abrirSessao($sessao, $usuario);

        self::assertNotSame($idAntes, $sessao->id());
        self::assertNull($sessao->obter('_csrf'));
    }

    private function servico(?Relogio $relogio = null): AuthService
    {
        return new AuthService(
            new Usuario(),
            new TentativaLogin(),
            $relogio ?? new RelogioFixo(new DateTimeImmutable('2026-03-10 10:00:00'))
        );
    }

    private function criarEscritorio(bool $ativo = true): int
    {
        $stmt = $this->conexao()->prepare('INSERT INTO escritorios (nome, email, ativo) VALUES (:nome, :email, :ativo)');
        $stmt->execute([
            'nome' => 'Escritório de teste',
            'email' => 'contato' . bin2hex(random_bytes(4)) . '@exemplo.test',
            'ativo' => $ativo ? 1 : 0,
        ]);

        return (int) $this->conexao()->lastInsertId();
    }

    private function ativarModulo(int $escritorioId, string $modulo): void
    {
        $stmt = $this->conexao()->prepare('INSERT INTO escritorio_modulos (escritorio_id, modulo) VALUES (:e, :m)');
        $stmt->execute(['e' => $escritorioId, 'm' => $modulo]);
    }

    private function criarUsuario(
        int $escritorioId,
        string $email,
        string $perfil = 'ADMIN',
        bool $ativo = true,
        ?string $hash = null
    ): int {
        $stmt = $this->conexao()->prepare(
            'INSERT INTO usuarios (escritorio_id, nome, email, senha_hash, perfil, ativo)
             VALUES (:escritorio_id, :nome, :email, :senha_hash, :perfil, :ativo)'
        );
        $stmt->execute([
            'escritorio_id' => $escritorioId,
            'nome' => 'Usuária de teste',
            'email' => $email,
            'senha_hash' => $hash ?? password_hash(self::SENHA, PASSWORD_DEFAULT),
            'perfil' => $perfil,
            'ativo' => $ativo ? 1 : 0,
        ]);

        return (int) $this->conexao()->lastInsertId();
    }

    private function coluna(string $sql): string
    {
        $valor = $this->conexao()->query($sql)->fetchColumn();

        return (string) $valor;
    }
}
