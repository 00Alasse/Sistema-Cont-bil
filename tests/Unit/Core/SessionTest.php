<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Session;
use App\Support\RelogioFixo;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class SessionTest extends TestCase
{
    public function testArmazenaERecuperaValores(): void
    {
        $sessao = $this->sessao('2026-01-10 10:00:00');
        $sessao->iniciar();

        $sessao->definir('usuario_id', 7);

        self::assertSame(7, $sessao->obter('usuario_id'));
        self::assertSame('padrao', $sessao->obter('inexistente', 'padrao'));
        self::assertNull($sessao->obter('inexistente'));
    }

    public function testRemoveValores(): void
    {
        $sessao = $this->sessao('2026-01-10 10:00:00');
        $sessao->iniciar();
        $sessao->definir('chave', 'valor');

        $sessao->remover('chave');

        self::assertNull($sessao->obter('chave'));
    }

    public function testRegenerarTrocaOIdentificadoESemPerderOsDados(): void
    {
        $sessao = $this->sessao('2026-01-10 10:00:00');
        $sessao->iniciar();
        $sessao->definir('usuario_id', 7);
        $idAntes = $sessao->id();

        $sessao->regenerar();

        self::assertNotSame($idAntes, $sessao->id());
        self::assertSame(7, $sessao->obter('usuario_id'));
    }

    public function testDestruirApagaTodosOsDados(): void
    {
        $sessao = $this->sessao('2026-01-10 10:00:00');
        $sessao->iniciar();
        $sessao->definir('usuario_id', 7);
        $sessao->flash('sucesso', 'Olá');

        $sessao->destruir();

        self::assertNull($sessao->obter('usuario_id'));
        self::assertSame([], $sessao->consumirFlash());
    }

    public function testExpiraPorInatividadeEAvisaUmaVez(): void
    {
        $dados = [];
        $this->sessao('2026-01-10 10:00:00', 30, $dados)->iniciar();
        $dados['usuario_id'] = 7;

        $depois = $this->sessao('2026-01-10 10:31:00', 30, $dados);
        $depois->iniciar();

        self::assertNull($depois->obter('usuario_id'));
        $mensagens = $depois->consumirFlash();
        self::assertCount(1, $mensagens);
        self::assertSame('Sua sessão expirou', $mensagens[0]['mensagem']);
        self::assertSame([], $depois->consumirFlash());
    }

    public function testNaoExpiraDentroDoLimiteERenovaAAtividade(): void
    {
        $dados = [];
        $this->sessao('2026-01-10 10:00:00', 30, $dados)->iniciar();
        $dados['usuario_id'] = 7;

        $this->sessao('2026-01-10 10:30:00', 30, $dados)->iniciar();
        $terceira = $this->sessao('2026-01-10 11:00:00', 30, $dados);
        $terceira->iniciar();

        self::assertSame(7, $terceira->obter('usuario_id'));
        self::assertSame([], $terceira->consumirFlash());
    }

    public function testSessaoNovaNaoGeraAvisoDeExpiracao(): void
    {
        $sessao = $this->sessao('2026-01-10 10:00:00');
        $sessao->iniciar();

        self::assertSame([], $sessao->consumirFlash());
    }

    public function testFlashEConsumidoUmaUnicaVez(): void
    {
        $sessao = $this->sessao('2026-01-10 10:00:00');
        $sessao->iniciar();
        $sessao->flash('sucesso', 'Salvo');
        $sessao->flash('erro', 'Falhou');

        self::assertSame(
            [
                ['tipo' => 'sucesso', 'mensagem' => 'Salvo'],
                ['tipo' => 'erro', 'mensagem' => 'Falhou'],
            ],
            $sessao->consumirFlash()
        );
        self::assertSame([], $sessao->consumirFlash());
    }

    /**
     * @param array<string, mixed> $dados
     */
    private function sessao(string $agora, int $inatividadeMin = 30, array &$dados = []): Session
    {
        return Session::emArray(
            $dados,
            new RelogioFixo(new DateTimeImmutable($agora)),
            $inatividadeMin
        );
    }
}
