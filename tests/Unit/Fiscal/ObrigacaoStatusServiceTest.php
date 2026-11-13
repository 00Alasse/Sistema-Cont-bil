<?php

declare(strict_types=1);

namespace Tests\Unit\Fiscal;

use App\Exceptions\AcessoNegadoException;
use App\Exceptions\ConflitoException;
use App\Services\Fiscal\ObrigacaoStatusService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Regras puras da matriz de transições (API §4.2). Nenhum teste aqui usa o banco.
 */
final class ObrigacaoStatusServiceTest extends TestCase
{
    private const STATUS = ['PENDENTE', 'EM_ANDAMENTO', 'AGUARDANDO_CLIENTE', 'ENTREGUE', 'DISPENSADA'];

    /**
     * Matriz esperada, transcrita da API §4.2:
     * LIVRE = ✔; JUSTIFICATIVA = ✔ (justificativa); REABERTURA = ✔ A + justificativa; PROIBIDA = ✘ ou —.
     *
     * @var array<string, array<string, string>>
     */
    private const MATRIZ = [
        'PENDENTE' => [
            'PENDENTE' => 'PROIBIDA',
            'EM_ANDAMENTO' => 'LIVRE',
            'AGUARDANDO_CLIENTE' => 'LIVRE',
            'ENTREGUE' => 'LIVRE',
            'DISPENSADA' => 'JUSTIFICATIVA',
        ],
        'EM_ANDAMENTO' => [
            'PENDENTE' => 'LIVRE',
            'EM_ANDAMENTO' => 'PROIBIDA',
            'AGUARDANDO_CLIENTE' => 'LIVRE',
            'ENTREGUE' => 'LIVRE',
            'DISPENSADA' => 'JUSTIFICATIVA',
        ],
        'AGUARDANDO_CLIENTE' => [
            'PENDENTE' => 'PROIBIDA',
            'EM_ANDAMENTO' => 'LIVRE',
            'AGUARDANDO_CLIENTE' => 'PROIBIDA',
            'ENTREGUE' => 'LIVRE',
            'DISPENSADA' => 'JUSTIFICATIVA',
        ],
        'ENTREGUE' => [
            'PENDENTE' => 'PROIBIDA',
            'EM_ANDAMENTO' => 'REABERTURA',
            'AGUARDANDO_CLIENTE' => 'PROIBIDA',
            'ENTREGUE' => 'PROIBIDA',
            'DISPENSADA' => 'PROIBIDA',
        ],
        'DISPENSADA' => [
            'PENDENTE' => 'REABERTURA',
            'EM_ANDAMENTO' => 'PROIBIDA',
            'AGUARDANDO_CLIENTE' => 'PROIBIDA',
            'ENTREGUE' => 'PROIBIDA',
            'DISPENSADA' => 'PROIBIDA',
        ],
    ];

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function combinacoes(): iterable
    {
        foreach (self::MATRIZ as $de => $destinos) {
            foreach ($destinos as $para => $esperado) {
                yield $de . ' → ' . $para => [$de, $para, $esperado];
            }
        }
    }

    public function testAMatrizTem25Combinacoes(): void
    {
        self::assertCount(25, iterator_to_array(self::combinacoes()));
    }

    #[DataProvider('combinacoes')]
    public function testRegraDeCadaCombinacao(string $de, string $para, string $esperado): void
    {
        $regra = ObrigacaoStatusService::regra($de, $para);

        match ($esperado) {
            'PROIBIDA' => self::assertNull($regra),
            'LIVRE' => self::assertSame(['admin' => false, 'justificativa' => false], $regra),
            'JUSTIFICATIVA' => self::assertSame(['admin' => false, 'justificativa' => true], $regra),
            'REABERTURA' => self::assertSame(['admin' => true, 'justificativa' => true], $regra),
            default => self::fail('Categoria desconhecida na matriz do teste: ' . $esperado),
        };
    }

    #[DataProvider('combinacoes')]
    public function testVerificacaoDaTransicaoPorPerfil(string $de, string $para, string $esperado): void
    {
        if ($esperado === 'PROIBIDA') {
            $this->assertLanca(ConflitoException::class, $de, $para, true);
            $this->assertLanca(ConflitoException::class, $de, $para, false);

            return;
        }

        // ADMIN pode tudo o que a matriz permite.
        ObrigacaoStatusService::verificarTransicao($de, $para, true);

        if ($esperado === 'REABERTURA') {
            $this->assertLanca(AcessoNegadoException::class, $de, $para, false);
        } else {
            ObrigacaoStatusService::verificarTransicao($de, $para, false);
        }
        $this->addToAssertionCount(1);
    }

    public function testTransicaoProibidaUsaAMensagemCombinada(): void
    {
        try {
            ObrigacaoStatusService::verificarTransicao('ENTREGUE', 'DISPENSADA', true);
            self::fail('ENTREGUE → DISPENSADA é proibida.');
        } catch (ConflitoException $erro) {
            self::assertSame('Esta mudança de status não é permitida.', $erro->getMessage());
        }
    }

    public function testStatusDesconhecidoEhProibido(): void
    {
        self::assertNull(ObrigacaoStatusService::regra('PENDENTE', 'CANCELADA'));
        self::assertNull(ObrigacaoStatusService::regra('ARQUIVADA', 'PENDENTE'));
        self::assertSame([], ObrigacaoStatusService::destinosPermitidos('ARQUIVADA', true));
    }

    public function testDestinosOferecidosPorStatusEPerfil(): void
    {
        $esperadoColaborador = [
            'PENDENTE' => ['EM_ANDAMENTO', 'AGUARDANDO_CLIENTE', 'ENTREGUE', 'DISPENSADA'],
            'EM_ANDAMENTO' => ['PENDENTE', 'AGUARDANDO_CLIENTE', 'ENTREGUE', 'DISPENSADA'],
            'AGUARDANDO_CLIENTE' => ['EM_ANDAMENTO', 'ENTREGUE', 'DISPENSADA'],
            'ENTREGUE' => [],
            'DISPENSADA' => [],
        ];
        $esperadoAdmin = ['ENTREGUE' => ['EM_ANDAMENTO'], 'DISPENSADA' => ['PENDENTE']] + $esperadoColaborador;

        foreach (self::STATUS as $de) {
            self::assertSame($esperadoColaborador[$de], ObrigacaoStatusService::destinosPermitidos($de, false), $de);
            self::assertSame($esperadoAdmin[$de], ObrigacaoStatusService::destinosPermitidos($de, true), $de);
        }
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function transicoesComJustificativa(): iterable
    {
        yield 'PENDENTE → DISPENSADA' => ['PENDENTE', 'DISPENSADA'];
        yield 'EM_ANDAMENTO → DISPENSADA' => ['EM_ANDAMENTO', 'DISPENSADA'];
        yield 'AGUARDANDO_CLIENTE → DISPENSADA' => ['AGUARDANDO_CLIENTE', 'DISPENSADA'];
        yield 'reabrir entregue' => ['ENTREGUE', 'EM_ANDAMENTO'];
        yield 'reabrir dispensada' => ['DISPENSADA', 'PENDENTE'];
    }

    #[DataProvider('transicoesComJustificativa')]
    public function testJustificativaObrigatoriaQuandoAMatrizExige(string $de, string $para): void
    {
        $mensagem = ['justificativa' => ['Justificativa é obrigatória para esta mudança de status.']];

        self::assertSame($mensagem, ObrigacaoStatusService::errosDosCampos($de, $para, null, null));
        self::assertSame($mensagem, ObrigacaoStatusService::errosDosCampos($de, $para, '', null));
        self::assertSame($mensagem, ObrigacaoStatusService::errosDosCampos($de, $para, "  \t ", null));
        self::assertSame([], ObrigacaoStatusService::errosDosCampos($de, $para, 'Motivo registrado', null));
    }

    public function testJustificativaEhOpcionalNasDemaisTransicoes(): void
    {
        self::assertSame([], ObrigacaoStatusService::errosDosCampos('PENDENTE', 'EM_ANDAMENTO', null, null));
        self::assertSame([], ObrigacaoStatusService::errosDosCampos('PENDENTE', 'EM_ANDAMENTO', 'Começando', null));
    }

    public function testLimiteDaJustificativaEm500Caracteres(): void
    {
        self::assertSame([], ObrigacaoStatusService::errosDosCampos('PENDENTE', 'DISPENSADA', str_repeat('á', 500), null));
        self::assertSame(
            ['justificativa' => ['Justificativa deve ter no máximo 500 caracteres.']],
            ObrigacaoStatusService::errosDosCampos('PENDENTE', 'DISPENSADA', str_repeat('á', 501), null)
        );
        // O limite vale também quando a justificativa é opcional.
        self::assertSame(
            ['justificativa' => ['Justificativa deve ter no máximo 500 caracteres.']],
            ObrigacaoStatusService::errosDosCampos('PENDENTE', 'EM_ANDAMENTO', str_repeat('a', 501), null)
        );
    }

    public function testProtocoloOpcionalNaEntregaComLimiteDe100Caracteres(): void
    {
        self::assertSame([], ObrigacaoStatusService::errosDosCampos('PENDENTE', 'ENTREGUE', null, null));
        self::assertSame([], ObrigacaoStatusService::errosDosCampos('PENDENTE', 'ENTREGUE', null, str_repeat('9', 100)));
        self::assertSame(
            ['protocolo' => ['Protocolo deve ter no máximo 100 caracteres.']],
            ObrigacaoStatusService::errosDosCampos('PENDENTE', 'ENTREGUE', null, str_repeat('9', 101))
        );
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function destinosForaDaEntrega(): iterable
    {
        yield 'para PENDENTE' => ['EM_ANDAMENTO', 'PENDENTE'];
        yield 'para EM_ANDAMENTO' => ['PENDENTE', 'EM_ANDAMENTO'];
        yield 'para AGUARDANDO_CLIENTE' => ['PENDENTE', 'AGUARDANDO_CLIENTE'];
        yield 'para DISPENSADA' => ['PENDENTE', 'DISPENSADA'];
    }

    #[DataProvider('destinosForaDaEntrega')]
    public function testProtocoloForaDaEntregaEhRecusado(string $de, string $para): void
    {
        $erros = ObrigacaoStatusService::errosDosCampos($de, $para, 'Motivo', 'REC-123');

        self::assertSame(['protocolo' => ['Protocolo só é informado na entrega.']], $erros);
        // Protocolo só com espaços conta como não informado.
        self::assertSame([], ObrigacaoStatusService::errosDosCampos($de, $para, 'Motivo', '   '));
    }

    /**
     * @param class-string<\Throwable> $excecao
     */
    private function assertLanca(string $excecao, string $de, string $para, bool $ehAdmin): void
    {
        try {
            ObrigacaoStatusService::verificarTransicao($de, $para, $ehAdmin);
            self::fail(sprintf('%s → %s deveria lançar %s.', $de, $para, $excecao));
        } catch (ConflitoException | AcessoNegadoException $erro) {
            self::assertInstanceOf($excecao, $erro);
        }
    }
}
