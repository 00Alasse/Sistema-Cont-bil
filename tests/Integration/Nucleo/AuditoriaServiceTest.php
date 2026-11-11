<?php

declare(strict_types=1);

namespace Tests\Integration\Nucleo;

use App\Core\UsuarioLogado;
use App\Models\Nucleo\LogAtividade;
use App\Services\Nucleo\AuditoriaService;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use ReflectionClass;
use Tests\Integration\IntegrationTestCase;

final class AuditoriaServiceTest extends IntegrationTestCase
{
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testGravaDetalhesIpEVinculosDoUsuarioEAoEscritorio(): void
    {
        if (!class_exists(UsuarioLogado::class)) {
            class_alias(UsuarioLogadoF06Fixture::class, UsuarioLogado::class, false);
        }

        [$escritorioA, $escritorioB] = $this->criarEscritorios();
        $usuarioId = $this->criarUsuario($escritorioA, 'auditoria@exemplo.test');
        $usuario = $this->usuarioLogado($usuarioId, $escritorioA);
        $service = new AuditoriaService(new LogAtividade());
        $ipAnterior = $_SERVER['REMOTE_ADDR'] ?? null;
        $_SERVER['REMOTE_ADDR'] = '203.0.113.17';

        try {
            $service->registrar(
                $usuario,
                'empresa',
                42,
                'atualizar',
                [
                    'campo' => 'razao_social',
                    'de' => 'Nome anterior',
                    'para' => 'Nome atualizado',
                    'senha' => 'nao-registrar',
                    'credenciais' => ['access_token' => 'token-de-teste'],
                ]
            );
        } finally {
            if ($ipAnterior === null) {
                unset($_SERVER['REMOTE_ADDR']);
            } else {
                $_SERVER['REMOTE_ADDR'] = $ipAnterior;
            }
        }

        $stmt = $this->conexao()->prepare(
            'SELECT escritorio_id, usuario_id, entidade, entidade_id, acao, detalhes, ip
             FROM logs_atividade
             WHERE escritorio_id = :escritorio_id AND usuario_id = :usuario_id'
        );
        $stmt->execute(['escritorio_id' => $escritorioA, 'usuario_id' => $usuarioId]);
        $registro = $stmt->fetch();
        $stmtOutroEscritorio = $this->conexao()->prepare(
            'SELECT COUNT(*) FROM logs_atividade WHERE escritorio_id = :escritorio_id'
        );
        $stmtOutroEscritorio->execute(['escritorio_id' => $escritorioB]);

        self::assertIsArray($registro);
        self::assertSame($escritorioA, (int) $registro['escritorio_id']);
        self::assertSame($usuarioId, (int) $registro['usuario_id']);
        self::assertSame('empresa', $registro['entidade']);
        self::assertSame(42, (int) $registro['entidade_id']);
        self::assertSame('atualizar', $registro['acao']);
        self::assertSame('203.0.113.17', $registro['ip']);

        $detalhes = json_decode((string) $registro['detalhes'], true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('razao_social', $detalhes['campo']);
        self::assertSame('Nome anterior', $detalhes['de']);
        self::assertSame('Nome atualizado', $detalhes['para']);
        self::assertArrayNotHasKey('senha', $detalhes);
        self::assertArrayNotHasKey('credenciais', $detalhes);
        self::assertSame(0, (int) $stmtOutroEscritorio->fetchColumn());
    }

    /**
     * Cria um objeto da classe do contrato inicializando somente propriedades documentadas.
     */
    private function usuarioLogado(int $usuarioId, int $escritorioId): UsuarioLogado
    {
        $reflexao = new ReflectionClass(UsuarioLogado::class);
        /** @var UsuarioLogado $usuario */
        $usuario = $reflexao->newInstanceWithoutConstructor();
        $propriedades = [
            'id' => $usuarioId,
            'escritorioId' => $escritorioId,
            'perfil' => 'ADMIN',
            'empresaId' => null,
            'nome' => 'Usuário de teste',
            'modulos' => [],
        ];

        foreach ($propriedades as $nome => $valor) {
            $propriedade = $reflexao->getProperty($nome);
            $propriedade->setValue($usuario, $valor);
        }

        return $usuario;
    }

    /**
     * @return array{int, int}
     */
    private function criarEscritorios(): array
    {
        $stmt = $this->conexao()->prepare(
            'INSERT INTO escritorios (nome, email) VALUES (:nome, :email)'
        );
        $stmt->execute(['nome' => 'Escritório auditoria A', 'email' => 'a@exemplo.test']);
        $escritorioA = (int) $this->conexao()->lastInsertId();
        $stmt->execute(['nome' => 'Escritório auditoria B', 'email' => 'b@exemplo.test']);

        return [$escritorioA, (int) $this->conexao()->lastInsertId()];
    }

    private function criarUsuario(int $escritorioId, string $email): int
    {
        $stmt = $this->conexao()->prepare(
            'INSERT INTO usuarios (escritorio_id, nome, email, senha_hash, perfil)
             VALUES (:escritorio_id, :nome, :email, :senha_hash, :perfil)'
        );
        $stmt->execute([
            'escritorio_id' => $escritorioId,
            'nome' => 'Usuário de auditoria',
            'email' => $email,
            'senha_hash' => 'hash-ficticio-de-teste',
            'perfil' => 'ADMIN',
        ]);

        return (int) $this->conexao()->lastInsertId();
    }
}

final class UsuarioLogadoF06Fixture
{
    public int $id = 0;

    public int $escritorioId = 0;

    public string $perfil = '';

    public ?int $empresaId = null;

    public string $nome = '';

    /** @var list<string> */
    public array $modulos = [];
}
