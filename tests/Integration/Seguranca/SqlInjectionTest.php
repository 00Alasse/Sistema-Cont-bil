<?php

declare(strict_types=1);

namespace Tests\Integration\Seguranca;

use App\Support\Cnpj;
use Tests\E2E\E2ETestCase;
use Tests\Support\ClienteHttp;
use Tests\Support\FabricaCadastro;
use Tests\Support\FabricaFiscal;
use Tests\Support\RespostaHttp;

/**
 * SQL Injection nos filtros, na ordenação, no login e na gravação (ARCHITECTURE §6.7 e §8):
 * prepared statements tratam a entrada como dado e a ordenação só aceita a whitelist.
 *
 * Os ataques nunca podem: dar erro de SQL, trazer dados de outro escritório, revelar hashes de
 * senha, ignorar o filtro (devolver todas as linhas), mudar a ordem ou alterar tabelas.
 */
final class SqlInjectionTest extends E2ETestCase
{
    private const SENHA = 'Senha@123';

    private const ATAQUES = [
        "' OR '1'='1",
        "' OR 1=1 -- ",
        "%' OR '%'='",
        "x' UNION SELECT id, email, senha_hash FROM usuarios -- ",
        "'; DROP TABLE empresas; -- ",
        "\\' OR 1=1 #",
        '1 OR 1=1',
        '%',
        '_',
    ];

    public function testBuscaTextualTrataOAtaqueComoTextoLiteral(): void
    {
        $cenario = $this->cenario();
        $cliente = $this->entrar($cenario['admin']);
        $listagens = [
            '/empresas?q=' => '#href="/empresas/(\d+)"#',
            '/obrigacoes?q=' => '#href="/obrigacoes/(\d+)"#',
            '/tipos-obrigacao?q=' => '#href="/tipos-obrigacao/(\d+)/editar"#',
        ];

        foreach ($listagens as $prefixo => $linhas) {
            // Controle: a busca por um nome existente encontra registros.
            self::assertNotSame([], $this->ids($cliente->get($prefixo . rawurlencode('Q01 Alfa')), $linhas), $prefixo);

            foreach (self::ATAQUES as $ataque) {
                $resposta = $cliente->get($prefixo . rawurlencode($ataque));
                $this->assertRespostaSegura($resposta, $cenario, $prefixo . $ataque);
                self::assertSame([], $this->ids($resposta, $linhas), 'O ataque não pode virar "traga tudo": ' . $prefixo . $ataque);
            }
        }
        $this->assertTabelasIntactas($cenario);
    }

    public function testFiltrosDeIdEOpcaoNaoExecutamOAtaque(): void
    {
        $cenario = $this->cenario();
        $cliente = $this->entrar($cenario['admin']);
        $filtros = [
            '/empresas' => ['status', 'regime', 'responsavel_id', 'servico'],
            '/obrigacoes' => ['empresa_id', 'tipo_id', 'responsavel', 'status', 'situacao', 'competencia', 'de', 'ate', 'entregue_de', 'entregue_ate'],
            '/tipos-obrigacao' => ['esfera', 'periodicidade', 'ativo'],
            '/painel' => ['escopo'],
        ];

        foreach ($filtros as $caminho => $campos) {
            foreach ($campos as $campo) {
                foreach (self::ATAQUES as $ataque) {
                    $resposta = $cliente->get($caminho . '?' . $campo . '=' . rawurlencode($ataque));
                    $this->assertRespostaSegura($resposta, $cenario, $caminho . '?' . $campo . '=' . $ataque);
                }
            }
        }
        $this->assertTabelasIntactas($cenario);
    }

    public function testOrdenacaoForaDaWhitelistMantemAOrdemPadrao(): void
    {
        $cenario = $this->cenario();
        $cliente = $this->entrar($cenario['admin']);
        $ataques = [
            'razao_social; DROP TABLE empresas',
            '(SELECT SLEEP(5))',
            'e.razao_social ASC, (SELECT 1)',
            'IF(1=1, e.cnpj, e.razao_social)',
            '-vencimento; -- ',
            '3',
            'e.senha_hash',
        ];
        // Valores aceitos pela API em cada listagem: o ataque só pode produzir uma dessas ordens.
        $listagens = [
            '/empresas' => ['#href="/empresas/(\d+)"#', ['', 'criado_em']],
            '/obrigacoes' => ['#href="/obrigacoes/(\d+)"#', ['', '-vencimento', 'competencia', '-competencia', 'empresa', '-empresa']],
        ];

        foreach ($listagens as $caminho => [$linhas, $validos]) {
            $permitidas = [];
            foreach ($validos as $valido) {
                $permitidas[] = $this->ids($cliente->get($caminho . '?ordem=' . $valido), $linhas);
            }
            self::assertCount(3, $permitidas[0], $caminho);
            foreach ($ataques as $ataque) {
                $inicio = microtime(true);
                $resposta = $cliente->get($caminho . '?ordem=' . rawurlencode($ataque));
                $duracao = microtime(true) - $inicio;

                $this->assertRespostaSegura($resposta, $cenario, $caminho . '?ordem=' . $ataque);
                self::assertContains($this->ids($resposta, $linhas), $permitidas, 'Ordenação fora da whitelist: ' . $ataque);
                // SLEEP executado em cada linha levaria pelo menos 15 s.
                self::assertLessThan(5.0, $duracao, 'A expressão de ordenação não pode ser executada: ' . $ataque);
            }
        }
        $this->assertTabelasIntactas($cenario);
    }

    public function testLoginComInjecaoNaoAutentica(): void
    {
        $cenario = $this->cenario();
        $email = $this->email($cenario['admin']);
        $tentativas = [
            ["' OR '1'='1' -- ", 'qualquer'],
            [$email . "' -- ", 'qualquer'],
            [$email, "' OR '1'='1"],
            [$email, "x' OR 1=1 -- "],
        ];

        foreach ($tentativas as [$emailAtaque, $senhaAtaque]) {
            $cliente = $this->cliente();
            $token = ClienteHttp::extrairCsrf($cliente->get('/login')->corpo);
            self::assertNotNull($token);
            $resposta = $cliente->post('/login', ['_csrf' => $token, 'email' => $emailAtaque, 'senha' => $senhaAtaque]);

            self::assertSame(422, $resposta->status, $emailAtaque . ' / ' . $senhaAtaque);
            self::assertStringNotContainsString('SQLSTATE', $resposta->corpo);
            self::assertSame(302, $cliente->get('/_teste/protegida')->status, 'O ataque não pode abrir sessão.');
        }
    }

    public function testTextoComSqlEGravadoEExibidoLiteralmente(): void
    {
        $cenario = $this->cenario();
        $cliente = $this->entrar($cenario['admin']);
        $razao = "Robert'); DROP TABLE empresas; -- " . bin2hex(random_bytes(3));

        $token = ClienteHttp::extrairCsrf($cliente->get('/_teste/protegida')->corpo);
        self::assertNotNull($token);
        $resposta = $cliente->post('/empresas', [
            '_csrf' => $token,
            'razao_social' => $razao,
            'cnpj' => Cnpj::gerarFicticio(random_int(1, 99_999_999)),
            'regime_tributario' => 'MEI',
        ]);

        self::assertSame(302, $resposta->status);
        $stmt = $this->banco()->prepare('SELECT razao_social FROM empresas WHERE escritorio_id = :escritorio AND razao_social = :razao');
        $stmt->execute(['escritorio' => $cenario['escritorio'], 'razao' => $razao]);
        self::assertSame($razao, $stmt->fetchColumn());
        self::assertStringContainsString(htmlspecialchars($razao, ENT_QUOTES, 'UTF-8'), $cliente->get('/empresas')->corpo);
        self::assertSame(4, $this->contar('empresas', $cenario['escritorio']));
    }

    /**
     * @param array{escritorio: int, admin: int, marca: string, empresas: int, obrigacoes: int, tipos: int} $cenario
     */
    private function assertRespostaSegura(RespostaHttp $resposta, array $cenario, string $onde): void
    {
        self::assertSame(200, $resposta->status, $onde);
        self::assertStringNotContainsString('SQLSTATE', $resposta->corpo, $onde);
        self::assertStringNotContainsString('$2y$', $resposta->corpo, 'Hash de senha exposto em ' . $onde);
        self::assertStringNotContainsString($cenario['marca'], $resposta->corpo, 'Dado de outro escritório em ' . $onde);
    }

    /**
     * @param array{escritorio: int, admin: int, marca: string, empresas: int, obrigacoes: int, tipos: int} $cenario
     */
    private function assertTabelasIntactas(array $cenario): void
    {
        self::assertSame($cenario['empresas'], $this->contar('empresas', $cenario['escritorio']));
        self::assertSame($cenario['obrigacoes'], $this->contar('obrigacoes', $cenario['escritorio']));
        self::assertSame($cenario['tipos'], $this->contar('tipos_obrigacao', $cenario['escritorio']));
        self::assertSame(1, $this->contar('usuarios', $cenario['escritorio']));
    }

    /**
     * IDs das linhas da listagem, na ordem em que aparecem (sem repetição).
     *
     * @return list<int>
     */
    private function ids(RespostaHttp $resposta, string $padrao): array
    {
        preg_match_all($padrao, $resposta->corpo, $achados);

        return array_values(array_unique(array_map('intval', $achados[1])));
    }

    /**
     * Escritório com três empresas (nomes fora da ordem de criação), um tipo por empresa e uma
     * obrigação em cada; e outro escritório cujos nomes levam uma marca que nunca pode aparecer.
     *
     * @return array{escritorio: int, admin: int, marca: string, empresas: int, obrigacoes: int, tipos: int}
     */
    private function cenario(): array
    {
        $cadastro = new FabricaCadastro($this->banco());
        $fiscal = new FabricaFiscal($this->banco());
        $escritorio = $cadastro->criarEscritorio();
        $admin = $cadastro->criarUsuario($escritorio, 'ADMIN');
        $sufixo = bin2hex(random_bytes(3));
        foreach (['Q01 Charlie', 'Q01 Alfa', 'Q01 Bravo'] as $i => $nome) {
            $empresa = $cadastro->criarEmpresa($escritorio, Cnpj::gerarFicticio(random_int(1, 99_999_999)), $nome . ' ' . $sufixo);
            $tipo = $fiscal->criarTipo($escritorio, ['nome' => $nome . ' tipo ' . $sufixo]);
            $fiscal->criarObrigacao($escritorio, $empresa, $tipo, $admin, ['vencimento' => '2026-02-' . (20 + $i)]);
        }

        $marca = 'MarcaSqlOutro' . bin2hex(random_bytes(4));
        $outro = $cadastro->criarEscritorio();
        $adminOutro = $cadastro->criarUsuario($outro, 'ADMIN');
        $empresaOutro = $cadastro->criarEmpresa($outro, Cnpj::gerarFicticio(random_int(1, 99_999_999)), $marca);
        $tipoOutro = $fiscal->criarTipo($outro, ['nome' => $marca]);
        $fiscal->criarObrigacao($outro, $empresaOutro, $tipoOutro, $adminOutro);

        return ['escritorio' => $escritorio, 'admin' => $admin, 'marca' => $marca, 'empresas' => 3, 'obrigacoes' => 3, 'tipos' => 3];
    }

    private function contar(string $tabela, int $escritorioId): int
    {
        $tabelas = ['empresas', 'obrigacoes', 'tipos_obrigacao', 'usuarios'];
        self::assertContains($tabela, $tabelas);
        $stmt = $this->banco()->prepare("SELECT COUNT(*) FROM {$tabela} WHERE escritorio_id = :escritorio");
        $stmt->execute(['escritorio' => $escritorioId]);

        return (int) $stmt->fetchColumn();
    }

    private function email(int $usuarioId): string
    {
        $stmt = $this->banco()->prepare('SELECT email FROM usuarios WHERE id = :id');
        $stmt->execute(['id' => $usuarioId]);

        return (string) $stmt->fetchColumn();
    }

    private function entrar(int $usuarioId): ClienteHttp
    {
        $cliente = $this->cliente();
        $token = ClienteHttp::extrairCsrf($cliente->get('/login')->corpo);
        self::assertNotNull($token);
        $resposta = $cliente->post('/login', ['_csrf' => $token, 'email' => $this->email($usuarioId), 'senha' => self::SENHA]);
        self::assertSame(302, $resposta->status);

        return $cliente;
    }
}
