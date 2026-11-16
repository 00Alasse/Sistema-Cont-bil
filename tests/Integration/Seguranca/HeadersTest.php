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
use Tests\Support\RespostaHttp;

/**
 * Cabeçalhos de segurança (ARCHITECTURE §8, linhas "Headers" e "XSS"), pelo HTTP: presentes
 * com o valor exato em toda página da tabela de rotas e em toda resposta de erro.
 */
final class HeadersTest extends E2ETestCase
{
    private const SENHA = 'Senha@123';

    private const HEADERS = [
        'X-Frame-Options' => 'DENY',
        'X-Content-Type-Options' => 'nosniff',
        'Referrer-Policy' => 'same-origin',
        'Content-Security-Policy' => "default-src 'self'; script-src 'self'; style-src 'self'; "
            . "img-src 'self' data:; frame-ancestors 'none'; form-action 'self'",
    ];

    /**
     * @return array<string, array{string}>
     */
    public static function rotasGet(): array
    {
        $casos = [];
        foreach ((new Modulos(new Router()))->carregar() as $modulo) {
            foreach ((array) $modulo['rotas'] as $rota) {
                if (is_array($rota) && ($rota[0] ?? null) === 'GET' && is_string($rota[1] ?? null)) {
                    $casos['GET ' . $rota[1]] = [$rota[1]];
                }
            }
        }

        return $casos;
    }

    #[DataProvider('rotasGet')]
    public function testPaginaTrazOsHeadersESemScriptInline(string $padrao): void
    {
        $cenario = $this->cenario();
        $resposta = $this->entrar($cenario['admin'])->get($this->caminho($padrao, $cenario));

        $this->assertHeadersDeSeguranca($resposta, 'GET ' . $padrao);
        // A CSP bloqueia JS inline; a página não pode depender dele.
        self::assertDoesNotMatchRegularExpression('#<script(?![^>]*\bsrc=)[^>]*>#i', $resposta->corpo, 'Script inline em ' . $padrao);
        self::assertDoesNotMatchRegularExpression('#<[a-z][^>]*\son[a-z]+\s*=#i', $resposta->corpo, 'Evento inline em ' . $padrao);
    }

    public function testRespostasDeErroERedirecionamentoTambemTrazemOsHeaders(): void
    {
        $cenario = $this->cenario();
        $outro = $this->cenario();
        $admin = $this->entrar($cenario['admin']);
        $clienteFinal = $this->entrar((new FabricaCadastro($this->banco()))->criarUsuario($cenario['escritorio'], 'CLIENTE', $cenario['empresas']));
        $loginInvalido = $this->cliente();
        $token = ClienteHttp::extrairCsrf($loginInvalido->get('/login')->corpo);
        self::assertNotNull($token);

        $respostas = [
            302 => $this->cliente()->get('/painel'),
            403 => $clienteFinal->get('/painel'),
            404 => $admin->get('/empresas/' . $outro['empresas']),
            405 => $admin->get('/logout'),
            419 => $admin->post('/logout', []),
            422 => $loginInvalido->post('/login', ['_csrf' => $token, 'email' => 'invalido', 'senha' => '']),
        ];
        $respostas[404 . ' sem rota'] = $this->cliente()->get('/caminho-que-nao-existe-' . bin2hex(random_bytes(3)));

        foreach ($respostas as $esperado => $resposta) {
            self::assertSame((int) $esperado, $resposta->status, (string) $esperado);
            $this->assertHeadersDeSeguranca($resposta, 'status ' . $esperado);
        }
    }

    public function testPoliticaDeConteudoNaoLiberaScriptInlineNemEval(): void
    {
        $csp = (string) $this->cliente()->get('/login')->header('Content-Security-Policy');

        self::assertStringNotContainsString('unsafe-inline', $csp);
        self::assertStringNotContainsString('unsafe-eval', $csp);
        self::assertStringNotContainsString('*', $csp);
    }

    private function assertHeadersDeSeguranca(RespostaHttp $resposta, string $onde): void
    {
        foreach (self::HEADERS as $nome => $valor) {
            self::assertSame([$valor], $resposta->headers[strtolower($nome)] ?? [], $nome . ' em ' . $onde);
        }
        if ($resposta->status !== 302) {
            self::assertSame('text/html; charset=UTF-8', $resposta->header('Content-Type'), 'Content-Type em ' . $onde);
        }
    }

    /**
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
}
