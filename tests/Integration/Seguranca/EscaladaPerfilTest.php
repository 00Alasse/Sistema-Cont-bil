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
 * Escalada de perfil (ARCHITECTURE §7, matriz de perfis, e §11.6), pelo HTTP.
 *
 * Os casos saem do middleware "perfil:" de cada rota registrada: quem não está na lista recebe
 * 403 e nada é gravado. COLABORADOR não vira ADMIN; CLIENTE não entra nas rotas do escritório.
 */
final class EscaladaPerfilTest extends E2ETestCase
{
    private const SENHA = 'Senha@123';

    private const TABELAS = ['empresas', 'tipos_obrigacao', 'empresa_tipos_obrigacao', 'obrigacoes', 'logs_atividade', 'solicitacoes_documentos', 'avaliacoes'];

    /**
     * @return array<string, array{string, string}>
     */
    public static function rotasProibidasAoColaborador(): array
    {
        return self::rotasQueNaoAceitam('COLABORADOR');
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function rotasProibidasAoCliente(): array
    {
        return self::rotasQueNaoAceitam('CLIENTE');
    }

    #[DataProvider('rotasProibidasAoColaborador')]
    public function testColaboradorRecebe403NasRotasDeAdmin(string $metodo, string $padrao): void
    {
        $cenario = $this->cenario();
        $colaborador = (new FabricaCadastro($this->banco()))->criarUsuario($cenario['escritorio'], 'COLABORADOR');

        $this->assertNegadoSemEfeito($this->entrar($colaborador), $metodo, $padrao, $cenario);
    }

    #[DataProvider('rotasProibidasAoCliente')]
    public function testClienteRecebe403NasRotasDoEscritorio(string $metodo, string $padrao): void
    {
        $cenario = $this->cenario();
        // O cliente é da própria empresa usada na URL: nem assim ele entra nas telas do escritório.
        $cliente = (new FabricaCadastro($this->banco()))->criarUsuario($cenario['escritorio'], 'CLIENTE', $cenario['empresas']);

        $this->assertNegadoSemEfeito($this->entrar($cliente), $metodo, $padrao, $cenario);
    }

    public function testColaboradorNaoReabreObrigacaoEntregue(): void
    {
        $cenario = $this->cenario(['status' => 'ENTREGUE']);
        $colaborador = $this->entrar((new FabricaCadastro($this->banco()))->criarUsuario($cenario['escritorio'], 'COLABORADOR'));
        $estado = $this->estado($cenario['escritorio']);

        $resposta = $colaborador->post('/obrigacoes/' . $cenario['obrigacoes'] . '/status', [
            '_csrf' => $this->token($colaborador),
            // ENTREGUE → EM_ANDAMENTO é a reabertura, só de ADMIN (ObrigacaoStatusService::TRANSICOES).
            'status' => 'EM_ANDAMENTO',
            'justificativa' => 'Tentativa de reabrir sem ser ADMIN',
            'versao' => '1',
        ]);

        self::assertSame(403, $resposta->status);
        self::assertSame($estado, $this->estado($cenario['escritorio']));
    }

    public function testPerfilEnviadoPelaRequisicaoNaoMudaOPerfilDaSessao(): void
    {
        $cenario = $this->cenario();
        $colaborador = (new FabricaCadastro($this->banco()))->criarUsuario($cenario['escritorio'], 'COLABORADOR');
        $cliente = $this->entrar($colaborador);
        $campos = ['perfil' => 'ADMIN', 'auth_usuario_id' => (string) $cenario['admin'], 'escritorio_id' => '1'];

        $cliente->post('/empresas', ['_csrf' => $this->token($cliente)] + $campos);

        self::assertSame(403, $cliente->get('/_teste/admin?' . http_build_query($campos))->status);
        self::assertSame('COLABORADOR', $this->perfil($colaborador));
        self::assertSame(200, $cliente->get('/_teste/equipe')->status);
    }

    public function testRebaixamentoDePerfilValeNaProximaRequisicao(): void
    {
        $cenario = $this->cenario();
        $cliente = $this->entrar($cenario['admin']);
        self::assertSame(200, $cliente->get('/_teste/admin')->status);

        $this->banco()->prepare("UPDATE usuarios SET perfil = 'COLABORADOR' WHERE id = :id")->execute(['id' => $cenario['admin']]);

        self::assertSame(403, $cliente->get('/_teste/admin')->status);
        self::assertSame(403, $cliente->get('/tipos-obrigacao/novo')->status);
    }

    /**
     * @param array{escritorio: int, admin: int, empresas: int, tipos-obrigacao: int, obrigacoes: int, solicitacoes: int} $cenario
     */
    private function assertNegadoSemEfeito(ClienteHttp $cliente, string $metodo, string $padrao, array $cenario): void
    {
        $caminho = $this->caminho($padrao, $cenario);
        $estado = $this->estado($cenario['escritorio']);

        $resposta = $metodo === 'GET'
            ? $cliente->get($caminho)
            : $cliente->post($caminho, ['_csrf' => $this->token($cliente)] + $this->camposPlausiveis($cenario));

        self::assertSame(403, $resposta->status, $metodo . ' ' . $padrao);
        self::assertStringContainsString('Acesso negado', $resposta->corpo);
        self::assertSame($estado, $this->estado($cenario['escritorio']), 'Nada pode ser gravado por quem não tem o perfil.');
    }

    /**
     * Rotas cujo middleware "perfil:" não inclui o perfil informado.
     *
     * @return array<string, array{string, string}>
     */
    private static function rotasQueNaoAceitam(string $perfil): array
    {
        $roteador = new Router();
        $casos = [];
        foreach ((new Modulos($roteador))->carregar() as $modulo) {
            foreach ((array) $modulo['rotas'] as $rota) {
                if (!is_array($rota) || !is_string($rota[0] ?? null) || !is_string($rota[1] ?? null)) {
                    continue;
                }
                $exemplo = (string) preg_replace('/\{[A-Za-z_][A-Za-z0-9_]*\}/', '1', $rota[1]);
                foreach ($roteador->resolver($rota[0], $exemplo)->middlewares as $middleware) {
                    if (
                        is_string($middleware)
                        && str_starts_with($middleware, 'perfil:')
                        && !in_array($perfil, explode(',', substr($middleware, 7)), true)
                    ) {
                        $casos[strtoupper($rota[0]) . ' ' . $rota[1]] = [strtoupper($rota[0]), $rota[1]];
                    }
                }
            }
        }

        return $casos;
    }

    /**
     * @param array{escritorio: int, admin: int, empresas: int, tipos-obrigacao: int, obrigacoes: int} $cenario
     * @return array<string, string>
     */
    private function camposPlausiveis(array $cenario): array
    {
        return [
            'razao_social' => 'Empresa escalada ' . bin2hex(random_bytes(4)),
            'cnpj' => Cnpj::gerarFicticio(random_int(1, 99_999_999)),
            'regime_tributario' => 'MEI',
            'nome' => 'Tipo escalada ' . bin2hex(random_bytes(4)),
            'esfera' => 'FEDERAL',
            'periodicidade' => 'EVENTUAL',
            'meses_apos_competencia' => '0',
            'ativo' => '1',
            'empresa_id' => (string) $cenario['empresas'],
            'tipo_obrigacao_id' => (string) $cenario['tipos-obrigacao'],
            'competencia' => '2026-07',
            'vencimento' => '2026-08-20',
            'status' => 'ENTREGUE',
            'versao' => '1',
            'nota' => '5',
            'facilidade' => '5',
            'comentario' => 'Muito bom',
        ];
    }

    /**
     * @param array<string, mixed> $opcoesObrigacao
     * @return array{escritorio: int, admin: int, empresas: int, tipos-obrigacao: int, obrigacoes: int, solicitacoes: int}
     */
    private function cenario(array $opcoesObrigacao = []): array
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
        $obrigacao = $fiscal->criarObrigacao($escritorio, $empresa, $tipo, $admin, $opcoesObrigacao);

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

    private function perfil(int $usuarioId): string
    {
        $stmt = $this->banco()->prepare('SELECT perfil FROM usuarios WHERE id = :id');
        $stmt->execute(['id' => $usuarioId]);

        return (string) $stmt->fetchColumn();
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
