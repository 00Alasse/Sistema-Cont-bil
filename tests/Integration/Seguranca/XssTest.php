<?php

declare(strict_types=1);

namespace Tests\Integration\Seguranca;

use App\Core\Modulos;
use App\Core\Router;
use App\Support\Cnpj;
use Tests\E2E\E2ETestCase;
use Tests\Support\ClienteHttp;
use Tests\Support\FabricaCadastro;
use Tests\Support\FabricaFiscal;
use Tests\Support\RespostaHttp;

/**
 * XSS armazenado e refletido (ARCHITECTURE §8): todo texto vindo do banco ou da requisição
 * aparece escapado com e(), nunca como HTML ativo.
 *
 * Cada campo de texto recebe um ataque com o próprio nome; todas as páginas GET da tabela de
 * rotas são abertas e nenhuma pode conter o HTML do ataque sem escape.
 */
final class XssTest extends E2ETestCase
{
    private const SENHA = 'Senha@123';

    /**
     * Trechos que só existem na página se o ataque foi impresso sem escape.
     */
    private const TRECHOS_ATIVOS = ['<img src=x', '<script>alert(', 'onerror=alert(1)>'];

    public function testTextoArmazenadoApareceEscapadoEmTodasAsPaginas(): void
    {
        $cenario = $this->cenarioComAtaques();
        $cliente = $this->entrar($cenario['admin']);
        $this->registrarJustificativa($cliente, $cenario['obrigacoes']);
        $campos = ['razao_social', 'nome_fantasia', 'segmento', 'contato_nome', 'email', 'observacoes', 'usuario',
            'tipo_nome', 'tipo_descricao', 'protocolo', 'observacao', 'justificativa'];
        $exibidos = array_fill_keys($campos, 0);
        $paginas = 0;

        foreach (self::rotasGet() as $padrao) {
            $resposta = $cliente->get($this->caminho($padrao, $cenario));
            $paginas++;
            $this->assertSemHtmlAtivo($resposta, $padrao);
            foreach ($campos as $campo) {
                $exibidos[$campo] += substr_count($resposta->corpo, self::escapado(self::ataque($campo)));
            }
        }

        self::assertGreaterThan(5, $paginas);
        foreach ($exibidos as $campo => $vezes) {
            self::assertGreaterThan(0, $vezes, 'O campo ' . $campo . ' devia aparecer (escapado) em alguma página.');
        }
    }

    public function testValoresDaRequisicaoSaoDevolvidosEscapados(): void
    {
        $cenario = $this->cenarioComAtaques();
        $cliente = $this->entrar($cenario['admin']);
        $ataque = self::ataque('refletido');
        $parametro = rawurlencode($ataque);

        $caminhos = [
            '/empresas?q=' . $parametro . '&status=' . $parametro . '&regime=' . $parametro,
            '/obrigacoes?q=' . $parametro . '&competencia=' . $parametro . '&situacao=' . $parametro . '&ordem=' . $parametro,
            '/tipos-obrigacao?q=' . $parametro . '&esfera=' . $parametro,
            '/painel?escopo=' . $parametro,
            '/obrigacoes/nova?empresa_id=' . $parametro,
        ];
        foreach ($caminhos as $caminho) {
            $resposta = $cliente->get($caminho);
            self::assertSame(200, $resposta->status, $caminho);
            $this->assertSemHtmlAtivo($resposta, $caminho);
        }
        // A busca é mostrada de volta ao usuário: precisa vir escapada.
        self::assertStringContainsString(self::escapado($ataque), $cliente->get($caminhos[0])->corpo);

        self::assertSame(404, $cliente->get('/empresas/' . $parametro)->status);
        $this->assertSemHtmlAtivo($cliente->get('/empresas/' . $parametro), '/empresas/{ataque}');
    }

    public function testFormularioDevolvidoComErroEscapaOQueFoiDigitado(): void
    {
        $cenario = $this->cenarioComAtaques();
        $ataque = self::ataque('digitado');

        // Login com e-mail inválido volta com 422 e preserva o e-mail digitado.
        $visitante = $this->cliente();
        $token = ClienteHttp::extrairCsrf($visitante->get('/login')->corpo);
        self::assertNotNull($token);
        $login = $visitante->post('/login', ['_csrf' => $token, 'email' => $ataque, 'senha' => 'x']);
        self::assertSame(422, $login->status);
        $this->assertSemHtmlAtivo($login, 'POST /login');
        self::assertStringContainsString(self::escapado($ataque), $login->corpo);

        // Cadastro de empresa com CNPJ inválido volta com os demais campos preenchidos.
        $cliente = $this->entrar($cenario['admin']);
        $empresa = $cliente->post('/empresas', [
            '_csrf' => $this->token($cliente),
            'razao_social' => $ataque,
            'cnpj' => 'invalido',
            'regime_tributario' => 'MEI',
            'observacoes' => $ataque,
        ]);
        self::assertSame(422, $empresa->status);
        $this->assertSemHtmlAtivo($empresa, 'POST /empresas');
        self::assertStringContainsString(self::escapado($ataque), $empresa->corpo);
    }

    private static function ataque(string $campo): string
    {
        return '"><img src=x onerror=alert(1)><script>alert(\'' . $campo . '\')</script>';
    }

    /**
     * Mesmo escape do helper e() das views.
     */
    private static function escapado(string $texto): string
    {
        return htmlspecialchars($texto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function assertSemHtmlAtivo(RespostaHttp $resposta, string $onde): void
    {
        foreach (self::TRECHOS_ATIVOS as $trecho) {
            self::assertStringNotContainsString($trecho, $resposta->corpo, 'HTML do ataque sem escape em ' . $onde);
        }
    }

    /**
     * @return list<string>
     */
    private static function rotasGet(): array
    {
        $rotas = [];
        foreach ((new Modulos(new Router()))->carregar() as $modulo) {
            foreach ((array) $modulo['rotas'] as $rota) {
                if (is_array($rota) && ($rota[0] ?? null) === 'GET' && is_string($rota[1] ?? null)) {
                    $rotas[] = $rota[1];
                }
            }
        }

        return $rotas;
    }

    /**
     * Escritório em que todo campo de texto exibido nas telas guarda um ataque.
     *
     * @return array{escritorio: int, admin: int, empresas: int, tipos-obrigacao: int, obrigacoes: int}
     */
    private function cenarioComAtaques(): array
    {
        $cadastro = new FabricaCadastro($this->banco());
        $fiscal = new FabricaFiscal($this->banco());
        $escritorio = $cadastro->criarEscritorio();
        $admin = $cadastro->criarUsuario($escritorio, 'ADMIN');
        $this->banco()->prepare('UPDATE usuarios SET nome = :nome WHERE id = :id')
            ->execute(['nome' => self::ataque('usuario'), 'id' => $admin]);

        $empresa = $cadastro->criarEmpresa($escritorio, Cnpj::gerarFicticio(random_int(1, 99_999_999)), self::ataque('razao_social'), [
            'nome_fantasia' => self::ataque('nome_fantasia'),
            'segmento' => self::ataque('segmento'),
            'contato_nome' => self::ataque('contato_nome'),
            'email' => self::ataque('email'),
            'observacoes' => self::ataque('observacoes'),
            'responsavel_id' => $admin,
        ]);
        $tipo = $fiscal->criarTipo($escritorio, ['nome' => self::ataque('tipo_nome'), 'descricao' => self::ataque('tipo_descricao')]);
        $fiscal->criarVinculo($escritorio, $empresa, $tipo, $admin);
        $obrigacao = $fiscal->criarObrigacao($escritorio, $empresa, $tipo, $admin, [
            'responsavel_id' => $admin,
            'protocolo' => self::ataque('protocolo'),
            'observacao' => self::ataque('observacao'),
        ]);

        return [
            'escritorio' => $escritorio,
            'admin' => $admin,
            'empresas' => $empresa,
            'tipos-obrigacao' => $tipo,
            'obrigacoes' => $obrigacao,
        ];
    }

    /**
     * A justificativa vai para o histórico (auditoria) pelo fluxo real de mudança de status.
     */
    private function registrarJustificativa(ClienteHttp $cliente, int $obrigacao): void
    {
        $resposta = $cliente->post('/obrigacoes/' . $obrigacao . '/status', [
            '_csrf' => $this->token($cliente),
            'status' => 'DISPENSADA',
            'justificativa' => self::ataque('justificativa'),
            'versao' => '1',
        ]);
        self::assertSame(302, $resposta->status);
    }

    /**
     * @param array{escritorio: int, admin: int, empresas: int, tipos-obrigacao: int, obrigacoes: int} $cenario
     */
    private function caminho(string $padrao, array $cenario): string
    {
        if (!str_contains($padrao, '{')) {
            return $padrao;
        }

        $recurso = explode('/', ltrim($padrao, '/'))[0];
        $id = match ($recurso) {
            'empresas', 'tipos-obrigacao', 'obrigacoes' => $cenario[$recurso],
            default => self::fail('A rota ' . $padrao . ' recebe {id} de um recurso "' . $recurso . '" que este teste ainda não cria.'),
        };

        return str_replace('{id}', (string) $id, $padrao);
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
