<?php

declare(strict_types=1);

namespace Tests\E2E;

use App\Core\Config;
use App\Support\Cnpj;
use DateTimeImmutable;
use DateTimeZone;
use Tests\Support\ClienteHttp;
use Tests\Support\FabricaCadastro;
use Tests\Support\FabricaFiscal;

/**
 * GET /painel pelo HTTP (GE02). O servidor usa o relógio real e o banco é compartilhado
 * entre as classes E2E: os números são conferidos de forma relativa (antes e depois).
 */
final class PainelTest extends E2ETestCase
{
    private const SENHA = 'Senha@123';

    private const ADMIN = 'admin@exemplo.test';

    private const COLABORADORA = 'ana@exemplo.test';

    private const AVISO = 'Indicadores gerenciais, calculados só com os registros do escritório. Não atestam regularidade fiscal.';

    public function testAdminVePainelComEscopoTodosPorPadrao(): void
    {
        $resposta = $this->entrar(self::ADMIN)->get('/painel');

        self::assertSame(200, $resposta->status);
        self::assertStringContainsString('<h1>Painel do escritório</h1>', $resposta->corpo);
        self::assertStringContainsString('Mostrando: <strong>Todo o escritório</strong>', $resposta->corpo);
        self::assertStringContainsString('href="/painel?escopo=meus"', $resposta->corpo);
        self::assertStringContainsString(self::AVISO, $resposta->corpo);
        self::assertStringContainsString('href="/obrigacoes?situacao=vence_hoje"', $resposta->corpo);
        self::assertStringContainsString('href="/obrigacoes?situacao=vence_7d"', $resposta->corpo);
        self::assertStringContainsString('href="/obrigacoes?situacao=atrasada"', $resposta->corpo);
        [$inicio, $fim] = $this->mesAtual();
        $entregues = '/obrigacoes?entregue_de=' . $inicio . '&amp;entregue_ate=' . $fim;
        self::assertStringContainsString('href="' . $entregues . '"', $resposta->corpo);
        self::assertStringContainsString('href="' . $entregues . '&amp;situacao=entregue_no_prazo"', $resposta->corpo);
        self::assertStringContainsString('Carga por responsável', $resposta->corpo);
        self::assertStringContainsString('5 piores índices', $resposta->corpo);
        // Sem o módulo de documentos (DO01), não há seção nem zeros fictícios.
        self::assertStringNotContainsString('Documentos', $resposta->corpo);
        // Um único "Painel" no menu, vindo de gestao.php.
        self::assertSame(1, preg_match_all('#<span class="menu-icone"[^>]*>(?:(?!</span>).)*</span>\s*<span class="menu-rotulo">Painel</span>#s', $resposta->corpo));
    }

    public function testColaboradoraVeMeusPorPadraoEPodeAlternar(): void
    {
        $cliente = $this->entrar(self::COLABORADORA);

        $padrao = $cliente->get('/painel');
        self::assertSame(200, $padrao->status);
        self::assertStringContainsString('Mostrando: <strong>Minhas obrigações e empresas</strong>', $padrao->corpo);
        self::assertStringContainsString('href="/obrigacoes?situacao=vence_hoje&amp;responsavel=meus"', $padrao->corpo);

        $todos = $cliente->get('/painel?escopo=todos');
        self::assertStringContainsString('Mostrando: <strong>Todo o escritório</strong>', $todos->corpo);
        self::assertStringContainsString('href="/obrigacoes?situacao=vence_hoje"', $todos->corpo);

        $invalido = $cliente->get('/painel?escopo=qualquer');
        self::assertSame(200, $invalido->status);
        self::assertStringContainsString('Mostrando: <strong>Minhas obrigações e empresas</strong>', $invalido->corpo);

        $admin = $this->entrar(self::ADMIN);
        self::assertStringContainsString('Mostrando: <strong>Todo o escritório</strong>', $admin->get('/painel?escopo=qualquer')->corpo);
        self::assertStringContainsString('Mostrando: <strong>Minhas obrigações e empresas</strong>', $admin->get('/painel?escopo=meus')->corpo);
    }

    public function testNumeroDoIndicadorAcompanhaAListagemDoLink(): void
    {
        $admin = $this->entrar(self::ADMIN);
        $colaboradora = $this->entrar(self::COLABORADORA);
        $antesTodos = $this->numero($admin->get('/painel')->corpo, '/obrigacoes?situacao=vence_hoje');
        $antesMeus = $this->numero($colaboradora->get('/painel')->corpo, '/obrigacoes?situacao=vence_hoje&amp;responsavel=meus');
        $tipoNome = 'Vence hoje E2E ' . bin2hex(random_bytes(4));
        $hoje = $this->hoje()->format('Y-m-d');
        $this->criarObrigacao(
            ['competencia' => substr($hoje, 0, 8) . '01', 'vencimento' => $hoje, 'responsavel_id' => $this->usuarioId(self::COLABORADORA)],
            ['nome' => $tipoNome]
        );

        self::assertSame($antesTodos + 1, $this->numero($admin->get('/painel')->corpo, '/obrigacoes?situacao=vence_hoje'));
        self::assertSame(
            $antesMeus + 1,
            $this->numero($colaboradora->get('/painel')->corpo, '/obrigacoes?situacao=vence_hoje&amp;responsavel=meus')
        );

        $listagem = $admin->get('/obrigacoes?situacao=vence_hoje');
        self::assertSame(200, $listagem->status);
        self::assertStringContainsString('<td>' . $tipoNome . '</td>', $listagem->corpo);
    }

    public function testCargaMostraInativoComSufixoESemResponsavelSemLink(): void
    {
        $inativo = (new FabricaCadastro($this->banco()))->criarUsuario($this->escritorioId(), 'COLABORADOR', null, false);
        $ontem = $this->hoje()->modify('-1 day')->format('Y-m-d');
        $this->criarObrigacao(['competencia' => substr($ontem, 0, 8) . '01', 'vencimento' => $ontem, 'responsavel_id' => $inativo]);
        $this->criarObrigacao(['competencia' => substr($ontem, 0, 8) . '01', 'vencimento' => $ontem]);

        $corpo = $this->entrar(self::ADMIN)->get('/painel')->corpo;

        self::assertStringContainsString('(inativo)', $corpo);
        self::assertStringContainsString('href="/obrigacoes?responsavel=' . $inativo . '&amp;situacao=atrasada"', $corpo);
        self::assertMatchesRegularExpression(
            '#<th scope="row">Sem responsável</th>\s*<td><span class="indicador-valor">[1-9][0-9]*</span></td>#',
            $corpo
        );
    }

    public function testPioresIndicesMostramEmpresaEscapadaComLink(): void
    {
        $empresa = (new FabricaCadastro($this->banco()))->criarEmpresa(
            $this->escritorioId(),
            Cnpj::gerarFicticio(random_int(1, 99_999_999)),
            '<b>Pior</b> Ltda ' . bin2hex(random_bytes(3))
        );
        // Atrasada há mais de um ano: sem histórico na janela, mas vermelha (aparece primeiro nos piores).
        $antiga = $this->hoje()->modify('-400 days')->format('Y-m-d');
        $fiscal = new FabricaFiscal($this->banco());
        $fiscal->criarObrigacao($this->escritorioId(), $empresa, $fiscal->criarTipo($this->escritorioId()), $this->usuarioId(self::ADMIN), [
            'competencia' => substr($antiga, 0, 8) . '01',
            'vencimento' => $antiga,
        ]);

        $corpo = $this->entrar(self::ADMIN)->get('/painel')->corpo;

        self::assertStringContainsString('href="/empresas/' . $empresa . '">&lt;b&gt;Pior&lt;/b&gt; Ltda', $corpo);
        self::assertStringNotContainsString('<b>Pior</b>', $corpo);
        self::assertStringContainsString('Obrigação atrasada há mais de 30 dias', $corpo);
    }

    public function testPioresIndicesExibemRecomendacaoDeAcaoPreventivaOuImediata(): void
    {
        $empresa = (new FabricaCadastro($this->banco()))->criarEmpresa(
            $this->escritorioId(),
            Cnpj::gerarFicticio(random_int(1, 99_999_999)),
            'Empresa Alerta ' . bin2hex(random_bytes(3))
        );
        $antiga = $this->hoje()->modify('-45 days')->format('Y-m-d');
        $fiscal = new FabricaFiscal($this->banco());
        $fiscal->criarObrigacao($this->escritorioId(), $empresa, $fiscal->criarTipo($this->escritorioId()), $this->usuarioId(self::ADMIN), [
            'competencia' => substr($antiga, 0, 8) . '01',
            'vencimento' => $antiga,
        ]);

        $resposta = $this->entrar(self::ADMIN)->get('/painel');
        self::assertSame(200, $resposta->status);
        self::assertStringContainsString('Recomendação', $resposta->corpo);
        self::assertStringContainsString('Ação imediata: regularizar', $resposta->corpo);
        self::assertStringContainsString('href="/obrigacoes?empresa_id=' . $empresa . '&amp;situacao=atrasada"', $resposta->corpo);
    }

    public function testClienteRecebe403ESemSessaoVaiParaOLogin(): void
    {
        self::assertSame(403, $this->entrar('cliente.padaria@exemplo.test')->get('/painel')->status);

        $semSessao = $this->cliente()->get('/painel');
        self::assertSame(302, $semSessao->status);
        self::assertStringEndsWith('/login', (string) $semSessao->location());
    }

    /**
     * Lê o número do indicador cujo link é exatamente $href (HTML já escapado).
     */
    private function numero(string $corpo, string $href): int
    {
        $padrao = '#<a class="indicador-valor" href="' . preg_quote($href, '#') . '">(\d+)</a>#';
        self::assertSame(1, preg_match($padrao, $corpo, $achado), 'Indicador não encontrado: ' . $href);

        return (int) $achado[1];
    }

    /**
     * @return array{string, string}
     */
    private function mesAtual(): array
    {
        $hoje = $this->hoje();

        return [$hoje->modify('first day of this month')->format('Y-m-d'), $hoje->modify('last day of this month')->format('Y-m-d')];
    }

    private function hoje(): DateTimeImmutable
    {
        return new DateTimeImmutable('today', new DateTimeZone((string) Config::obter('app.timezone', 'America/Sao_Paulo')));
    }

    private function entrar(string $email): ClienteHttp
    {
        $cliente = $this->cliente();
        $token = ClienteHttp::extrairCsrf($cliente->get('/login')->corpo);
        self::assertNotNull($token);
        $resposta = $cliente->post('/login', ['_csrf' => $token, 'email' => $email, 'senha' => self::SENHA]);
        self::assertSame(302, $resposta->status);

        return $cliente;
    }

    /**
     * @param array<string, mixed> $opcoes
     * @param array<string, mixed> $opcoesTipo
     */
    private function criarObrigacao(array $opcoes, array $opcoesTipo = []): int
    {
        $escritorio = $this->escritorioId();
        $empresa = (new FabricaCadastro($this->banco()))->criarEmpresa(
            $escritorio,
            Cnpj::gerarFicticio(random_int(1, 99_999_999)),
            'Empresa GE02 ' . bin2hex(random_bytes(3))
        );
        $fiscal = new FabricaFiscal($this->banco());

        return $fiscal->criarObrigacao($escritorio, $empresa, $fiscal->criarTipo($escritorio, $opcoesTipo), $this->usuarioId(self::ADMIN), $opcoes);
    }

    private function escritorioId(): int
    {
        $stmt = $this->banco()->prepare('SELECT escritorio_id FROM usuarios WHERE email = :email');
        $stmt->execute(['email' => self::ADMIN]);

        return (int) $stmt->fetchColumn();
    }

    private function usuarioId(string $email): int
    {
        $stmt = $this->banco()->prepare('SELECT id FROM usuarios WHERE email = :email');
        $stmt->execute(['email' => $email]);

        return (int) $stmt->fetchColumn();
    }
}
