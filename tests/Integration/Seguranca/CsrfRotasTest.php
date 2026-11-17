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
 * Proteção CSRF em todo POST (ARCHITECTURE §8 e §11.6), pelo HTTP.
 *
 * Os casos saem da tabela real de rotas: toda rota POST, sem token, com token inventado ou com
 * o token de outra sessão, responde 419 e não altera nada, com ou sem usuário logado.
 */
final class CsrfRotasTest extends E2ETestCase
{
    private const SENHA = 'Senha@123';

    private const TABELAS = ['empresas', 'tipos_obrigacao', 'empresa_tipos_obrigacao', 'obrigacoes', 'logs_atividade', 'solicitacoes_documentos', 'avaliacoes'];

    /**
     * @return array<string, array{string}>
     */
    public static function rotasPost(): array
    {
        $casos = [];
        foreach (self::tabelaDeRotas() as [$metodo, $padrao]) {
            if ($metodo !== 'GET') {
                $casos[$metodo . ' ' . $padrao] = [$padrao];
            }
        }

        return $casos;
    }

    #[DataProvider('rotasPost')]
    public function testPostSemTokenValidoResponde419ESemEfeito(string $padrao): void
    {
        $cenario = $this->cenario();
        $caminho = $this->caminho($padrao, $cenario);
        $estado = $this->estado($cenario['escritorio']);
        $logado = $this->entrar($cenario['admin']);
        $outraSessao = $this->entrar($this->novoAdmin($cenario['escritorio']));
        $campos = $this->camposPlausiveis($cenario);

        $tentativas = [
            'sem sessão e sem token' => $this->cliente()->post($caminho, $campos),
            'logado e sem token' => $logado->post($caminho, $campos),
            'token vazio' => $logado->post($caminho, ['_csrf' => ''] + $campos),
            'token inventado' => $logado->post($caminho, ['_csrf' => bin2hex(random_bytes(32))] + $campos),
            'token de outra sessão' => $logado->post($caminho, ['_csrf' => $this->token($outraSessao)] + $campos),
        ];

        foreach ($tentativas as $descricao => $resposta) {
            self::assertSame(419, $resposta->status, 'POST ' . $padrao . ' (' . $descricao . ')');
            self::assertStringContainsString('Solicitação expirada', $resposta->corpo);
        }
        self::assertSame($estado, $this->estado($cenario['escritorio']), 'Nenhum POST recusado pode gravar dados.');
        // As recusas não derrubam a sessão de quem foi alvo do ataque.
        self::assertSame(200, $logado->get('/_teste/protegida')->status);
    }

    public function testTokenCsrfNaoEAceitoNaQueryString(): void
    {
        $cenario = $this->cenario();
        $cliente = $this->entrar($cenario['admin']);
        $estado = $this->estado($cenario['escritorio']);

        $resposta = $cliente->post(
            '/empresas/' . $cenario['empresas'] . '/excluir?_csrf=' . $this->token($cliente),
            ['versao' => '1']
        );

        self::assertSame(419, $resposta->status);
        self::assertSame($estado, $this->estado($cenario['escritorio']));
    }

    public function testTodoFormularioPostDasPaginasTrazOCampoCsrf(): void
    {
        $cenario = $this->cenario();
        $cliente = $this->entrar($cenario['admin']);
        $formularios = 0;

        foreach (self::tabelaDeRotas() as [$metodo, $padrao]) {
            if ($metodo !== 'GET') {
                continue;
            }
            $resposta = $cliente->get($this->caminho($padrao, $cenario));
            preg_match_all('#<form\b[^>]*\bmethod="post"[^>]*>(.*?)</form>#is', $resposta->corpo, $achados);
            foreach ($achados[1] as $conteudo) {
                $formularios++;
                self::assertMatchesRegularExpression('/name="_csrf"\s+value="[0-9a-f]{64}"/', $conteudo, 'Formulário POST sem token em ' . $padrao);
            }
        }

        self::assertGreaterThan(0, $formularios, 'Nenhum formulário POST foi encontrado nas páginas.');
    }

    /**
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
     * Campos que fariam a ação dar certo se o token não fosse exigido (assim o teste também
     * mostra que nada foi gravado).
     *
     * @param array{escritorio: int, admin: int, empresas: int, tipos-obrigacao: int, obrigacoes: int} $cenario
     * @return array<string, string>
     */
    private function camposPlausiveis(array $cenario): array
    {
        return [
            'email' => 'invasor@exemplo.test',
            'senha' => self::SENHA,
            'razao_social' => 'Empresa CSRF ' . bin2hex(random_bytes(4)),
            'cnpj' => Cnpj::gerarFicticio(random_int(1, 99_999_999)),
            'regime_tributario' => 'MEI',
            'nome' => 'Tipo CSRF ' . bin2hex(random_bytes(4)),
            'esfera' => 'FEDERAL',
            'periodicidade' => 'EVENTUAL',
            'meses_apos_competencia' => '0',
            'ativo' => '1',
            'empresa_id' => (string) $cenario['empresas'],
            'tipo_obrigacao_id' => (string) $cenario['tipos-obrigacao'],
            'competencia' => '2026-07',
            'vencimento' => '2026-08-20',
            'status' => 'ENTREGUE',
            'protocolo' => 'CSRF-1',
            'versao' => '1',
            'titulo' => 'Doc Teste CSRF',
            'prazo' => '2026-12-31',
            'motivo_recusa' => 'Motivo teste',
        ];
    }

    /**
     * @return array{escritorio: int, admin: int, empresas: int, tipos-obrigacao: int, obrigacoes: int, solicitacoes: int}
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

        $stmt = $this->banco()->prepare("INSERT INTO solicitacoes_documentos (escritorio_id, empresa_id, titulo, prazo, status, criado_por) VALUES (:escritorio, :empresa, 'Doc Teste', CURRENT_DATE, 'aberta', :admin)");
        $stmt->execute(['escritorio' => $escritorio, 'empresa' => $empresa, 'admin' => $admin]);
        $solicitacao = (int) $this->banco()->lastInsertId();

        return [
            'escritorio' => $escritorio,
            'admin' => $admin,
            'empresas' => $empresa,
            'tipos-obrigacao' => $tipo,
            'obrigacoes' => $obrigacao,
            'solicitacoes' => $solicitacao,
        ];
    }

    private function novoAdmin(int $escritorioId): int
    {
        return (new FabricaCadastro($this->banco()))->criarUsuario($escritorioId, 'ADMIN');
    }

    /**
     * @param array{escritorio: int, admin: int, empresas: int, tipos-obrigacao: int, obrigacoes: int, solicitacoes: int} $cenario
     */
    private function caminho(string $padrao, array $cenario): string
    {
        if (!str_contains($padrao, '{')) {
            return $padrao;
        }

        $recurso = explode('/', ltrim($padrao, '/'))[0];
        $id = match ($recurso) {
            'empresas', 'tipos-obrigacao', 'obrigacoes' => $cenario[$recurso],
            'solicitacoes', 'portal' => $cenario['solicitacoes'],
            default => self::fail('A rota ' . $padrao . ' recebe {id} de um recurso "' . $recurso . '" que este teste ainda não cria.'),
        };

        return str_replace('{id}', (string) $id, $padrao);
    }

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
