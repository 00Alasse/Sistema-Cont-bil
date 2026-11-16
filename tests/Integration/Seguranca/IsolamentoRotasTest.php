<?php

declare(strict_types=1);

namespace Tests\Integration\Seguranca;

use App\Core\Modulos;
use App\Core\Router;
use App\Support\Cnpj;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\E2E\E2ETestCase;
use Tests\Support\ClienteHttp;
use Tests\Support\FabricaCadastro;
use Tests\Support\FabricaFiscal;

/**
 * Isolamento entre escritórios (ARCHITECTURE §7 e §11.6), pelo HTTP.
 *
 * Os casos saem da tabela real de rotas (config/modules): toda rota com {id} é chamada por um
 * ADMIN do escritório A com o ID de um registro do escritório B e deve responder 404, sem mudar
 * nada em B. Cada teste cria os próprios escritórios, então a ordem de execução não importa.
 */
final class IsolamentoRotasTest extends E2ETestCase
{
    private const SENHA = 'Senha@123';

    /**
     * Tabelas com escritorio_id cujo conteúdo compõe o "estado" de um escritório.
     */
    private const TABELAS = ['empresas', 'tipos_obrigacao', 'empresa_tipos_obrigacao', 'obrigacoes', 'logs_atividade'];

    /**
     * @return array<string, array{string, string}>
     */
    public static function rotasComId(): array
    {
        $casos = [];
        foreach (self::tabelaDeRotas() as [$metodo, $padrao]) {
            if (str_contains($padrao, '{')) {
                $casos[$metodo . ' ' . $padrao] = [$metodo, $padrao];
            }
        }

        return $casos;
    }

    #[DataProvider('rotasComId')]
    public function testRotaComIdDeOutroEscritorioResponde404(string $metodo, string $padrao): void
    {
        $meu = $this->cenario();
        $outro = $this->cenario();
        $cliente = $this->entrar($meu['admin']);
        $estadoDoOutro = $this->estado($outro['escritorio']);

        $resposta = $this->chamar($cliente, $metodo, $this->caminho($padrao, $outro));

        self::assertSame(404, $resposta->status, $metodo . ' ' . $padrao . ' com ID de outro escritório');
        self::assertStringContainsString('Página não encontrada', $resposta->corpo);
        self::assertSame($estadoDoOutro, $this->estado($outro['escritorio']), 'Nada do outro escritório pode mudar.');

        // Controle: com o ID do próprio escritório a rota existe (o 404 acima vem do isolamento).
        self::assertNotSame(404, $this->chamar($cliente, $metodo, $this->caminho($padrao, $meu))->status);
    }

    public function testListagensEFormulariosNaoMostramDadosDeOutroEscritorio(): void
    {
        $meu = $this->cenario();
        $outro = $this->cenario();
        $marca = $this->marcarOutroEscritorio($outro);
        $cliente = $this->entrar($meu['admin']);

        foreach (['/painel?escopo=todos', '/empresas', '/empresas/nova', '/obrigacoes', '/obrigacoes/nova', '/tipos-obrigacao'] as $caminho) {
            $resposta = $cliente->get($caminho);
            self::assertSame(200, $resposta->status, $caminho);
            self::assertStringNotContainsString($marca, $resposta->corpo, $caminho);
        }
    }

    public function testFiltrosComIdsDeOutroEscritorioNaoRevelamRegistros(): void
    {
        $meu = $this->cenario();
        $outro = $this->cenario();
        $marca = $this->marcarOutroEscritorio($outro);
        $cliente = $this->entrar($meu['admin']);

        $caminhos = [
            '/obrigacoes?empresa_id=' . $outro['empresas'],
            '/obrigacoes?tipo_id=' . $outro['tipos-obrigacao'],
            '/obrigacoes?responsavel=' . $outro['admin'],
            '/empresas?responsavel_id=' . $outro['admin'],
            '/obrigacoes/nova?empresa_id=' . $outro['empresas'],
        ];
        foreach ($caminhos as $caminho) {
            $resposta = $cliente->get($caminho);
            self::assertSame(200, $resposta->status, $caminho);
            self::assertStringNotContainsString($marca, $resposta->corpo, $caminho);
            self::assertStringNotContainsString('href="/obrigacoes/' . $outro['obrigacoes'] . '"', $resposta->corpo, $caminho);
        }
    }

    public function testEscritorioEnviadoNoFormularioEIgnorado(): void
    {
        $meu = $this->cenario();
        $outro = $this->cenario();
        $cliente = $this->entrar($meu['admin']);
        $estadoDoOutro = $this->estado($outro['escritorio']);
        $razao = 'Empresa adulterada Q01 ' . bin2hex(random_bytes(4));

        $resposta = $cliente->post('/empresas', [
            '_csrf' => $this->token($cliente),
            'razao_social' => $razao,
            'cnpj' => Cnpj::gerarFicticio(random_int(1, 99_999_999)),
            'regime_tributario' => 'MEI',
            'escritorio_id' => (string) $outro['escritorio'],
        ]);

        self::assertSame(302, $resposta->status);
        $stmt = $this->banco()->prepare('SELECT escritorio_id FROM empresas WHERE razao_social = :razao');
        $stmt->execute(['razao' => $razao]);
        self::assertSame([$meu['escritorio']], array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN)));
        self::assertSame($estadoDoOutro, $this->estado($outro['escritorio']));
    }

    public function testReferenciasDeOutroEscritorioNoFormularioSaoRecusadas(): void
    {
        $meu = $this->cenario();
        $outro = $this->cenario();
        $cliente = $this->entrar($meu['admin']);
        $estadoDoMeu = $this->estado($meu['escritorio']);
        $estadoDoOutro = $this->estado($outro['escritorio']);

        $resposta = $cliente->post('/obrigacoes', [
            '_csrf' => $this->token($cliente),
            'empresa_id' => (string) $outro['empresas'],
            'tipo_obrigacao_id' => (string) $outro['tipos-obrigacao'],
            'competencia' => '2026-05',
            'vencimento' => '2026-06-20',
            'responsavel_id' => (string) $outro['admin'],
        ]);

        self::assertSame(422, $resposta->status);
        self::assertSame($estadoDoMeu, $this->estado($meu['escritorio']));
        self::assertSame($estadoDoOutro, $this->estado($outro['escritorio']));
    }

    /**
     * Método, padrão e middlewares efetivos de cada rota registrada (inclusive o "modulo:" que o
     * carregador acrescenta aos módulos contratáveis).
     *
     * @return list<array{string, string, list<string>}>
     */
    private static function tabelaDeRotas(): array
    {
        $roteador = new Router();
        $rotas = [];
        foreach ((new Modulos($roteador))->carregar() as $modulo) {
            foreach ((array) $modulo['rotas'] as $rota) {
                if (!is_array($rota) || !is_string($rota[0] ?? null) || !is_string($rota[1] ?? null)) {
                    continue;
                }
                $exemplo = (string) preg_replace('/\{[A-Za-z_][A-Za-z0-9_]*\}/', '1', $rota[1]);
                $middlewares = array_values(array_filter($roteador->resolver($rota[0], $exemplo)->middlewares, 'is_string'));
                $rotas[] = [strtoupper($rota[0]), $rota[1], $middlewares];
            }
        }

        return $rotas;
    }

    /**
     * Um escritório completo: ADMIN, empresa, tipo, vínculo e obrigação. As chaves de recurso
     * são o primeiro trecho das rotas que recebem {id}.
     *
     * @return array{escritorio: int, admin: int, empresas: int, tipos-obrigacao: int, obrigacoes: int}
     */
    private function cenario(): array
    {
        $cadastro = new FabricaCadastro($this->banco());
        $fiscal = new FabricaFiscal($this->banco());
        $escritorio = $cadastro->criarEscritorio();
        $admin = $cadastro->criarUsuario($escritorio, 'ADMIN');
        $empresa = $cadastro->criarEmpresa(
            $escritorio,
            Cnpj::gerarFicticio(random_int(1, 99_999_999)),
            'Empresa Q01 ' . bin2hex(random_bytes(4))
        );
        $tipo = $fiscal->criarTipo($escritorio);
        $fiscal->criarVinculo($escritorio, $empresa, $tipo);
        $obrigacao = $fiscal->criarObrigacao($escritorio, $empresa, $tipo, $admin);

        return [
            'escritorio' => $escritorio,
            'admin' => $admin,
            'empresas' => $empresa,
            'tipos-obrigacao' => $tipo,
            'obrigacoes' => $obrigacao,
        ];
    }

    /**
     * Troca os nomes visíveis do outro escritório por uma marca única, procurada nas respostas.
     *
     * @param array{escritorio: int, admin: int, empresas: int, tipos-obrigacao: int, obrigacoes: int} $outro
     */
    private function marcarOutroEscritorio(array $outro): string
    {
        $marca = 'MarcaOutroEscritorio' . bin2hex(random_bytes(4));
        $comandos = [
            'UPDATE empresas SET razao_social = :marca, nome_fantasia = :marca2 WHERE id = :id',
            'UPDATE tipos_obrigacao SET nome = :marca, descricao = :marca2 WHERE id = :id',
            'UPDATE usuarios SET nome = :marca, email = CONCAT(:marca2, \'@exemplo.test\') WHERE id = :id',
        ];
        $ids = [$outro['empresas'], $outro['tipos-obrigacao'], $outro['admin']];
        foreach ($comandos as $i => $sql) {
            $this->banco()->prepare($sql)->execute(['marca' => $marca, 'marca2' => $marca, 'id' => $ids[$i]]);
        }

        return $marca;
    }

    /**
     * @param array{escritorio: int, admin: int, empresas: int, tipos-obrigacao: int, obrigacoes: int} $cenario
     */
    private function caminho(string $padrao, array $cenario): string
    {
        $recurso = explode('/', ltrim($padrao, '/'))[0];
        $id = match ($recurso) {
            'empresas', 'tipos-obrigacao', 'obrigacoes' => $cenario[$recurso],
            default => self::fail(
                'A rota ' . $padrao . ' recebe {id}, mas este teste não sabe criar o recurso "' . $recurso
                . '" em outro escritório. Acrescente-o ao cenário para manter a rota coberta.'
            ),
        };
        $caminho = str_replace('{id}', (string) $id, $padrao);
        self::assertStringNotContainsString('{', $caminho, 'Parâmetro de rota sem valor de teste: ' . $padrao);

        return $caminho;
    }

    private function chamar(ClienteHttp $cliente, string $metodo, string $caminho): \Tests\Support\RespostaHttp
    {
        if ($metodo === 'GET') {
            return $cliente->get($caminho);
        }

        // Token válido: o 404 tem de vir do isolamento, não da proteção CSRF.
        return $cliente->post($caminho, ['_csrf' => $this->token($cliente), 'versao' => '1']);
    }

    /**
     * Retrato do conteúdo de um escritório, para provar que uma requisição não o alterou.
     */
    private function estado(int $escritorioId): string
    {
        $retrato = [];
        foreach (self::TABELAS as $tabela) {
            $stmt = $this->banco()->prepare("SELECT * FROM {$tabela} WHERE escritorio_id = :escritorio ORDER BY 1, 2");
            $stmt->execute(['escritorio' => $escritorioId]);
            $retrato[$tabela] = $stmt->fetchAll();
        }

        return hash('sha256', (string) json_encode($retrato));
    }

    private function entrar(int $usuarioId): ClienteHttp
    {
        $stmt = $this->banco()->prepare('SELECT email FROM usuarios WHERE id = :id');
        $stmt->execute(['id' => $usuarioId]);
        $cliente = $this->cliente();
        $token = ClienteHttp::extrairCsrf($cliente->get('/login')->corpo);
        self::assertNotNull($token);
        $resposta = $cliente->post('/login', ['_csrf' => $token, 'email' => (string) $stmt->fetchColumn(), 'senha' => self::SENHA]);
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
