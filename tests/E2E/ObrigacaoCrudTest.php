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
use Tests\Support\RespostaHttp;

/**
 * O servidor embutido usa o relógio real; por isso as datas "atrasadas" e "futuras"
 * são calculadas a partir de hoje, no mesmo fuso da aplicação.
 */
final class ObrigacaoCrudTest extends E2ETestCase
{
    private const SENHA = 'Senha@123';

    private const ADMIN = 'admin@exemplo.test';

    private const COLABORADORA = 'ana@exemplo.test';

    public function testAdminCriaObrigacaoComAuditoriaEVeODetalheEscapado(): void
    {
        $empresa = $this->criarEmpresa();
        $tipoNome = $this->nomeUnico('Guia E2E');
        $tipo = $this->criarTipo(['nome' => $tipoNome]);
        $cliente = $this->entrar(self::ADMIN);

        $resposta = $this->postarFormulario($cliente, '/obrigacoes/nova', '/obrigacoes', [
            'empresa_id' => (string) $empresa,
            'tipo_obrigacao_id' => (string) $tipo,
            'competencia' => '2026-01',
            'vencimento' => '2026-02-20',
            'responsavel_id' => (string) $this->usuarioId(self::ADMIN),
            'observacao' => '<script>alert("x")</script>',
            // Campos fora do contrato são ignorados.
            'status' => 'ENTREGUE',
            'escritorio_id' => '999',
        ]);

        self::assertSame(302, $resposta->status);
        $id = $this->idDaObrigacao($empresa, $tipo);
        self::assertGreaterThan(0, $id);
        self::assertStringEndsWith('/obrigacoes/' . $id, (string) $resposta->location());
        $obrigacao = $this->obrigacao($id);
        self::assertSame('PENDENTE', $obrigacao['status']);
        self::assertSame('2026-01-01', $obrigacao['competencia']);
        self::assertSame('2026-02-20', $obrigacao['vencimento']);
        self::assertSame($this->usuarioId(self::ADMIN), (int) $obrigacao['criado_por']);
        self::assertSame($this->escritorioId(self::ADMIN), (int) $obrigacao['escritorio_id']);
        self::assertSame(1, $this->contarAuditoria($id, 'criar'));

        $detalhe = $cliente->get('/obrigacoes/' . $id);
        self::assertSame(200, $detalhe->status);
        self::assertStringContainsString($tipoNome, $detalhe->corpo);
        self::assertStringContainsString('20/02/2026', $detalhe->corpo);
        self::assertStringContainsString('01/2026', $detalhe->corpo);
        self::assertStringContainsString('Pendente', $detalhe->corpo);
        self::assertStringNotContainsString('<script>alert', $detalhe->corpo);
        self::assertStringContainsString('&lt;script&gt;alert', $detalhe->corpo);
    }

    public function testFormularioPreSelecionaAEmpresaInformada(): void
    {
        $empresa = $this->criarEmpresa();
        $cliente = $this->entrar(self::ADMIN);

        $pagina = $cliente->get('/obrigacoes/nova?empresa_id=' . $empresa);

        self::assertSame(200, $pagina->status);
        self::assertStringContainsString('<option value="' . $empresa . '" selected>', $pagina->corpo);
    }

    public function testValidacaoRespondeComErrosPorCampo(): void
    {
        $cliente = $this->entrar(self::ADMIN);

        $vazio = $this->postarFormulario($cliente, '/obrigacoes/nova', '/obrigacoes', [
            'empresa_id' => '',
            'tipo_obrigacao_id' => '',
            'competencia' => '2026-13',
            'vencimento' => '20/02/2026',
        ]);

        self::assertSame(422, $vazio->status);
        self::assertStringContainsString('Empresa é obrigatório', $vazio->corpo);
        self::assertStringContainsString('Tipo de obrigação é obrigatório', $vazio->corpo);
        self::assertStringContainsString('competência válida no formato AAAA-MM', $vazio->corpo);
        self::assertStringContainsString('data válida no formato AAAA-MM-DD', $vazio->corpo);

        $antes = $this->postarFormulario($cliente, '/obrigacoes/nova', '/obrigacoes', [
            'empresa_id' => (string) $this->criarEmpresa(),
            'tipo_obrigacao_id' => (string) $this->criarTipo(),
            'competencia' => '2026-03',
            'vencimento' => '2026-02-28',
        ]);

        self::assertSame(422, $antes->status);
        self::assertStringContainsString('não pode ser anterior ao início da competência', $antes->corpo);
    }

    public function testVencimentoVazioVoltaComASugestaoCalculadaSemGravar(): void
    {
        $empresa = $this->criarEmpresa();
        $tipo = $this->criarTipo(['dia_vencimento' => 31, 'meses_apos_competencia' => 1]);
        $cliente = $this->entrar(self::ADMIN);

        $resposta = $this->postarFormulario($cliente, '/obrigacoes/nova', '/obrigacoes', [
            'empresa_id' => (string) $empresa,
            'tipo_obrigacao_id' => (string) $tipo,
            'competencia' => '2026-01',
            'vencimento' => '',
        ]);

        self::assertSame(422, $resposta->status);
        // Dia 31 num fevereiro comum vira o último dia do mês (ARCHITECTURE §10.2).
        self::assertStringContainsString('value="2026-02-28"', $resposta->corpo);
        self::assertStringContainsString('Confira o vencimento sugerido', $resposta->corpo);
        self::assertSame(0, $this->idDaObrigacao($empresa, $tipo));

        $semDia = $this->criarTipo(['periodicidade' => 'EVENTUAL', 'dia_vencimento' => null]);
        $semSugestao = $this->postarFormulario($cliente, '/obrigacoes/nova', '/obrigacoes', [
            'empresa_id' => (string) $empresa,
            'tipo_obrigacao_id' => (string) $semDia,
            'competencia' => '2026-01',
            'vencimento' => '',
        ]);

        self::assertSame(422, $semSugestao->status);
        self::assertStringContainsString('Vencimento é obrigatório', $semSugestao->corpo);
    }

    public function testMesmoTipoNaMesmaCompetenciaResponde422(): void
    {
        $empresa = $this->criarEmpresa();
        $tipo = $this->criarTipo();
        (new FabricaFiscal($this->banco()))->criarObrigacao(
            $this->escritorioId(self::ADMIN),
            $empresa,
            $tipo,
            $this->usuarioId(self::ADMIN),
            ['competencia' => '2026-01-01']
        );
        $cliente = $this->entrar(self::ADMIN);

        $resposta = $this->postarFormulario($cliente, '/obrigacoes/nova', '/obrigacoes', [
            'empresa_id' => (string) $empresa,
            'tipo_obrigacao_id' => (string) $tipo,
            'competencia' => '2026-01',
            'vencimento' => '2026-02-20',
        ]);

        self::assertSame(422, $resposta->status);
        self::assertStringContainsString('Já existe obrigação deste tipo nesta competência', $resposta->corpo);
    }

    public function testTipoInativoEReferenciasInvalidasRespondem422(): void
    {
        $cadastro = new FabricaCadastro($this->banco());
        $outroEscritorio = $cadastro->criarEscritorio();
        $empresaDeFora = $cadastro->criarEmpresa($outroEscritorio, Cnpj::gerarFicticio(random_int(1, 99_999_999)), 'Empresa de fora');
        $responsavelInativo = $cadastro->criarUsuario($this->escritorioId(self::ADMIN), 'COLABORADOR', null, false);
        $cliente = $this->entrar(self::ADMIN);

        $resposta = $this->postarFormulario($cliente, '/obrigacoes/nova', '/obrigacoes', [
            'empresa_id' => (string) $empresaDeFora,
            'tipo_obrigacao_id' => (string) $this->criarTipo(['ativo' => 0]),
            'competencia' => '2026-01',
            'vencimento' => '2026-02-20',
            'responsavel_id' => (string) $responsavelInativo,
        ]);

        self::assertSame(422, $resposta->status);
        self::assertStringContainsString('Selecione uma empresa deste escritório', $resposta->corpo);
        self::assertStringContainsString('Selecione um tipo de obrigação ativo deste escritório', $resposta->corpo);
        self::assertStringContainsString('Selecione um responsável ativo deste escritório', $resposta->corpo);
        self::assertSame(0, $this->contarObrigacoesDaEmpresa($empresaDeFora));
    }

    public function testColaboradoraEditaVencimentoResponsavelEObservacaoSemMudarStatus(): void
    {
        $empresa = $this->criarEmpresa();
        $id = $this->criarObrigacao($empresa, ['status' => 'EM_ANDAMENTO', 'competencia' => '2026-01-01']);
        $cliente = $this->entrar(self::COLABORADORA);

        $resposta = $this->postarFormulario($cliente, '/obrigacoes/' . $id . '/editar', '/obrigacoes/' . $id, [
            'vencimento' => '2026-02-27',
            'responsavel_id' => (string) $this->usuarioId(self::COLABORADORA),
            'observacao' => 'Aguardando extrato',
            'versao' => '1',
            // Nada disto pode ser alterado pela edição.
            'status' => 'ENTREGUE',
            'competencia' => '2030-01',
            'empresa_id' => (string) $this->criarEmpresa(),
        ]);

        self::assertSame(302, $resposta->status);
        self::assertStringEndsWith('/obrigacoes/' . $id, (string) $resposta->location());
        $obrigacao = $this->obrigacao($id);
        self::assertSame('2026-02-27', $obrigacao['vencimento']);
        self::assertSame($this->usuarioId(self::COLABORADORA), (int) $obrigacao['responsavel_id']);
        self::assertSame('Aguardando extrato', $obrigacao['observacao']);
        self::assertSame('EM_ANDAMENTO', $obrigacao['status']);
        self::assertSame('2026-01-01', $obrigacao['competencia']);
        self::assertSame($empresa, (int) $obrigacao['empresa_id']);
        self::assertSame(2, (int) $obrigacao['versao']);
        self::assertSame(1, $this->contarAuditoria($id, 'editar'));
    }

    public function testEdicaoValidaVencimentoContraACompetencia(): void
    {
        $id = $this->criarObrigacao($this->criarEmpresa(), ['competencia' => '2026-01-01']);
        $cliente = $this->entrar(self::ADMIN);

        $resposta = $this->postarFormulario($cliente, '/obrigacoes/' . $id . '/editar', '/obrigacoes/' . $id, [
            'vencimento' => '2025-12-31',
            'responsavel_id' => '',
            'observacao' => '',
            'versao' => '1',
        ]);

        self::assertSame(422, $resposta->status);
        self::assertStringContainsString('não pode ser anterior ao início da competência', $resposta->corpo);
        self::assertSame(1, (int) $this->obrigacao($id)['versao']);
    }

    public function testEdicaoComVersaoDesatualizadaResponde409(): void
    {
        $id = $this->criarObrigacao($this->criarEmpresa());
        $this->banco()->prepare('UPDATE obrigacoes SET versao = 2 WHERE id = :id')->execute(['id' => $id]);
        $cliente = $this->entrar(self::ADMIN);

        $resposta = $this->postarFormulario($cliente, '/obrigacoes/' . $id . '/editar', '/obrigacoes/' . $id, [
            'vencimento' => '2026-02-25',
            'responsavel_id' => '',
            'observacao' => '',
            'versao' => '1',
        ]);

        self::assertSame(409, $resposta->status);
        self::assertSame('2026-02-20', $this->obrigacao($id)['vencimento']);
        self::assertSame(0, $this->contarAuditoria($id, 'editar'));
    }

    public function testSoAdminExcluiObrigacao(): void
    {
        $id = $this->criarObrigacao($this->criarEmpresa());
        $colaboradora = $this->entrar(self::COLABORADORA);

        $detalhe = $colaboradora->get('/obrigacoes/' . $id);
        self::assertSame(200, $detalhe->status);
        self::assertStringNotContainsString('/obrigacoes/' . $id . '/excluir', $detalhe->corpo);
        $token = ClienteHttp::extrairCsrf($detalhe->corpo);
        self::assertNotNull($token);
        self::assertSame(403, $colaboradora->post('/obrigacoes/' . $id . '/excluir', ['_csrf' => $token])->status);
        self::assertNotSame([], $this->obrigacao($id));

        $admin = $this->entrar(self::ADMIN);
        $resposta = $this->postarFormulario($admin, '/obrigacoes/' . $id, '/obrigacoes/' . $id . '/excluir', []);

        self::assertSame(302, $resposta->status);
        self::assertStringEndsWith('/obrigacoes', (string) $resposta->location());
        self::assertSame([], $this->obrigacao($id));
        self::assertSame(1, $this->contarAuditoria($id, 'excluir'));
    }

    public function testObrigacaoDeOutroEscritorioResponde404(): void
    {
        $cadastro = new FabricaCadastro($this->banco());
        $outroEscritorio = $cadastro->criarEscritorio();
        $usuarioDeFora = $cadastro->criarUsuario($outroEscritorio);
        $empresaDeFora = $cadastro->criarEmpresa($outroEscritorio, Cnpj::gerarFicticio(random_int(1, 99_999_999)), 'Empresa de fora');
        $fiscal = new FabricaFiscal($this->banco());
        $id = $fiscal->criarObrigacao($outroEscritorio, $empresaDeFora, $fiscal->criarTipo($outroEscritorio), $usuarioDeFora);
        $cliente = $this->entrar(self::ADMIN);

        self::assertSame(404, $cliente->get('/obrigacoes/' . $id)->status);
        self::assertSame(404, $cliente->get('/obrigacoes/' . $id . '/editar')->status);
        $token = ClienteHttp::extrairCsrf($cliente->get('/obrigacoes/nova')->corpo);
        self::assertNotNull($token);
        $edicao = $cliente->post('/obrigacoes/' . $id, [
            '_csrf' => $token,
            'vencimento' => '2026-02-25',
            'versao' => '1',
        ]);
        self::assertSame(404, $edicao->status);
        self::assertSame(404, $cliente->post('/obrigacoes/' . $id . '/excluir', ['_csrf' => $token])->status);
        self::assertSame('2026-02-20', $this->obrigacao($id)['vencimento']);
    }

    public function testListagemFiltraPorSituacaoEDestacaAtrasadas(): void
    {
        $empresa = $this->criarEmpresa();
        $hoje = $this->hoje();
        $vencida = $hoje->modify('-3 days');
        $atrasadaNome = $this->nomeUnico('Atrasada E2E');
        $futuraNome = $this->nomeUnico('Futura E2E');
        $this->criarObrigacao($empresa, [
            'competencia' => $vencida->modify('first day of -1 month')->format('Y-m-d'),
            'vencimento' => $vencida->format('Y-m-d'),
        ], ['nome' => $atrasadaNome]);
        $this->criarObrigacao($empresa, [
            'competencia' => $hoje->modify('first day of this month')->format('Y-m-d'),
            'vencimento' => $hoje->modify('+30 days')->format('Y-m-d'),
        ], ['nome' => $futuraNome]);
        $cliente = $this->entrar(self::ADMIN);

        $todas = $cliente->get('/obrigacoes?empresa_id=' . $empresa);
        self::assertSame(200, $todas->status);
        // Os nomes dos tipos também aparecem no <select> do filtro: a checagem olha a célula da tabela.
        self::assertStringContainsString($this->celula($atrasadaNome), $todas->corpo);
        self::assertStringContainsString($this->celula($futuraNome), $todas->corpo);
        self::assertSame(1, substr_count($todas->corpo, 'semaforo-vermelho'));

        $atrasadas = $cliente->get('/obrigacoes?empresa_id=' . $empresa . '&situacao=atrasada');
        self::assertSame(200, $atrasadas->status);
        self::assertStringContainsString($this->celula($atrasadaNome), $atrasadas->corpo);
        self::assertStringNotContainsString($this->celula($futuraNome), $atrasadas->corpo);
        self::assertStringContainsString('Atrasada', $atrasadas->corpo);
    }

    public function testFiltroMeusMostraSoAsObrigacoesDoUsuarioLogado(): void
    {
        $empresa = $this->criarEmpresa();
        $minhaNome = $this->nomeUnico('Minha E2E');
        $deOutroNome = $this->nomeUnico('Do Bruno E2E');
        $this->criarObrigacao($empresa, ['responsavel_id' => $this->usuarioId(self::COLABORADORA)], ['nome' => $minhaNome]);
        $this->criarObrigacao($empresa, ['responsavel_id' => $this->usuarioId('bruno@exemplo.test')], ['nome' => $deOutroNome]);
        $cliente = $this->entrar(self::COLABORADORA);

        $resposta = $cliente->get('/obrigacoes?responsavel=meus&empresa_id=' . $empresa);

        self::assertSame(200, $resposta->status);
        self::assertStringContainsString($this->celula($minhaNome), $resposta->corpo);
        self::assertStringNotContainsString($this->celula($deOutroNome), $resposta->corpo);
    }

    private function celula(string $texto): string
    {
        return '<td>' . $texto . '</td>';
    }

    public function testClienteNaoAcessaObrigacoesDoEscritorio(): void
    {
        $cliente = $this->entrar('cliente.padaria@exemplo.test');

        self::assertSame(403, $cliente->get('/obrigacoes')->status);
        self::assertSame(403, $cliente->get('/obrigacoes/nova')->status);
    }

    private function entrar(string $email): ClienteHttp
    {
        $cliente = $this->cliente();
        $login = $cliente->get('/login');
        $token = ClienteHttp::extrairCsrf($login->corpo);
        self::assertNotNull($token);
        $resposta = $cliente->post('/login', ['_csrf' => $token, 'email' => $email, 'senha' => self::SENHA]);
        self::assertSame(302, $resposta->status);

        return $cliente;
    }

    /**
     * @param array<string, string> $dados
     */
    private function postarFormulario(
        ClienteHttp $cliente,
        string $caminhoFormulario,
        string $caminhoPost,
        array $dados
    ): RespostaHttp {
        $pagina = $cliente->get($caminhoFormulario);
        self::assertSame(200, $pagina->status);
        $token = ClienteHttp::extrairCsrf($pagina->corpo);
        self::assertNotNull($token);
        $dados['_csrf'] = $token;

        return $cliente->post($caminhoPost, $dados);
    }

    private function hoje(): DateTimeImmutable
    {
        $fuso = new DateTimeZone((string) Config::obter('app.timezone', 'America/Sao_Paulo'));

        return new DateTimeImmutable('today', $fuso);
    }

    private function nomeUnico(string $prefixo): string
    {
        return $prefixo . ' ' . bin2hex(random_bytes(4));
    }

    /**
     * @param array<string, mixed> $opcoes
     */
    private function criarTipo(array $opcoes = []): int
    {
        return (new FabricaFiscal($this->banco()))->criarTipo($this->escritorioId(self::ADMIN), $opcoes);
    }

    private function criarEmpresa(): int
    {
        return (new FabricaCadastro($this->banco()))->criarEmpresa(
            $this->escritorioId(self::ADMIN),
            Cnpj::gerarFicticio(random_int(1, 99_999_999)),
            'Empresa FI03 ' . bin2hex(random_bytes(3))
        );
    }

    /**
     * @param array<string, mixed> $opcoes
     * @param array<string, mixed> $opcoesTipo
     */
    private function criarObrigacao(int $empresaId, array $opcoes = [], array $opcoesTipo = []): int
    {
        return (new FabricaFiscal($this->banco()))->criarObrigacao(
            $this->escritorioId(self::ADMIN),
            $empresaId,
            $this->criarTipo($opcoesTipo),
            $this->usuarioId(self::ADMIN),
            $opcoes
        );
    }

    private function escritorioId(string $email): int
    {
        $stmt = $this->banco()->prepare('SELECT escritorio_id FROM usuarios WHERE email = :email');
        $stmt->execute(['email' => $email]);

        return (int) $stmt->fetchColumn();
    }

    private function usuarioId(string $email): int
    {
        $stmt = $this->banco()->prepare('SELECT id FROM usuarios WHERE email = :email');
        $stmt->execute(['email' => $email]);

        return (int) $stmt->fetchColumn();
    }

    private function idDaObrigacao(int $empresaId, int $tipoId): int
    {
        $stmt = $this->banco()->prepare(
            'SELECT id FROM obrigacoes WHERE empresa_id = :empresa AND tipo_obrigacao_id = :tipo'
        );
        $stmt->execute(['empresa' => $empresaId, 'tipo' => $tipoId]);

        return (int) $stmt->fetchColumn();
    }

    private function contarObrigacoesDaEmpresa(int $empresaId): int
    {
        $stmt = $this->banco()->prepare('SELECT COUNT(*) FROM obrigacoes WHERE empresa_id = :empresa');
        $stmt->execute(['empresa' => $empresaId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return array<string, mixed>
     */
    private function obrigacao(int $id): array
    {
        $stmt = $this->banco()->prepare('SELECT * FROM obrigacoes WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $obrigacao = $stmt->fetch();

        return is_array($obrigacao) ? $obrigacao : [];
    }

    private function contarAuditoria(int $id, string $acao): int
    {
        $stmt = $this->banco()->prepare(
            "SELECT COUNT(*) FROM logs_atividade
             WHERE entidade = 'obrigacao' AND entidade_id = :id AND acao = :acao"
        );
        $stmt->execute(['id' => $id, 'acao' => $acao]);

        return (int) $stmt->fetchColumn();
    }
}
