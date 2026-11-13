<?php

declare(strict_types=1);

namespace Tests\E2E;

use App\Support\Cnpj;
use Tests\Support\ClienteHttp;
use Tests\Support\FabricaCadastro;
use Tests\Support\FabricaFiscal;
use Tests\Support\RespostaHttp;

/**
 * Fluxo de status das obrigações pelo HTTP (FI04, ARCHITECTURE §11.2 passo 6).
 */
final class ObrigacaoStatusTest extends E2ETestCase
{
    private const SENHA = 'Senha@123';

    private const ADMIN = 'admin@exemplo.test';

    private const COLABORADORA = 'ana@exemplo.test';

    public function testColaboradoraEntregaComProtocoloEOHistoricoAparece(): void
    {
        $id = $this->criarObrigacao();
        $cliente = $this->entrar(self::COLABORADORA);

        $resposta = $this->mudarStatus($cliente, $id, ['status' => 'ENTREGUE', 'protocolo' => 'REC-E2E-77', 'versao' => '1']);

        self::assertSame(302, $resposta->status);
        self::assertStringEndsWith('/obrigacoes/' . $id, (string) $resposta->location());
        $obrigacao = $this->obrigacao($id);
        self::assertSame('ENTREGUE', $obrigacao['status']);
        self::assertSame('REC-E2E-77', $obrigacao['protocolo']);
        self::assertSame($this->usuarioId(self::COLABORADORA), (int) $obrigacao['entregue_por']);
        self::assertNotNull($obrigacao['entregue_em']);
        self::assertSame(1, $this->contarAuditoria($id, 'status:ENTREGUE'));

        $detalhe = $cliente->get('/obrigacoes/' . $id);
        self::assertSame(200, $detalhe->status);
        self::assertStringContainsString('Status atualizado.', $detalhe->corpo);
        self::assertStringContainsString('Histórico de status', $detalhe->corpo);
        self::assertStringContainsString('Pendente → Entregue', $detalhe->corpo);
        self::assertStringContainsString('REC-E2E-77', $detalhe->corpo);
        // Entregue: a colaboradora não pode reabrir, então não há formulário de status.
        self::assertStringNotContainsString('/obrigacoes/' . $id . '/status', $detalhe->corpo);
        self::assertStringContainsString('Não há mudança de status disponível', $detalhe->corpo);
    }

    public function testDispensaSemJustificativaResponde422EPreservaODigitado(): void
    {
        $id = $this->criarObrigacao();
        $cliente = $this->entrar(self::COLABORADORA);

        $resposta = $this->mudarStatus($cliente, $id, ['status' => 'DISPENSADA', 'justificativa' => '   ', 'versao' => '1']);

        self::assertSame(422, $resposta->status);
        self::assertStringContainsString('Justificativa é obrigatória para esta mudança de status.', $resposta->corpo);
        self::assertStringContainsString('<option value="DISPENSADA" selected>', $resposta->corpo);
        self::assertSame('PENDENTE', $this->obrigacao($id)['status']);
        self::assertSame(0, $this->contarAuditoria($id, 'status:DISPENSADA'));
    }

    public function testProtocoloForaDaEntregaResponde422(): void
    {
        $id = $this->criarObrigacao();
        $cliente = $this->entrar(self::COLABORADORA);

        $resposta = $this->mudarStatus($cliente, $id, ['status' => 'EM_ANDAMENTO', 'protocolo' => 'REC-1', 'versao' => '1']);

        self::assertSame(422, $resposta->status);
        self::assertStringContainsString('Protocolo só é informado na entrega.', $resposta->corpo);
        self::assertStringContainsString('value="REC-1"', $resposta->corpo);
        self::assertSame('PENDENTE', $this->obrigacao($id)['status']);
    }

    public function testStatusForaDaListaResponde422(): void
    {
        $id = $this->criarObrigacao();
        $cliente = $this->entrar(self::ADMIN);

        $resposta = $this->mudarStatus($cliente, $id, ['status' => 'ARQUIVADA', 'versao' => '1']);

        self::assertSame(422, $resposta->status);
        self::assertSame('PENDENTE', $this->obrigacao($id)['status']);
    }

    public function testSoAdminReabreComJustificativa(): void
    {
        $id = $this->criarObrigacao(['status' => 'ENTREGUE', 'protocolo' => 'REC-ANTIGO']);
        $colaboradora = $this->entrar(self::COLABORADORA);

        $negado = $this->mudarStatus($colaboradora, $id, ['status' => 'EM_ANDAMENTO', 'justificativa' => 'Retificar', 'versao' => '1']);
        self::assertSame(403, $negado->status);
        self::assertSame('ENTREGUE', $this->obrigacao($id)['status']);

        $admin = $this->entrar(self::ADMIN);
        $detalhe = $admin->get('/obrigacoes/' . $id);
        self::assertStringContainsString('<option value="EM_ANDAMENTO">', $detalhe->corpo);

        $reaberta = $this->mudarStatus($admin, $id, ['status' => 'EM_ANDAMENTO', 'justificativa' => 'Retificar a guia', 'versao' => '1']);

        self::assertSame(302, $reaberta->status);
        $obrigacao = $this->obrigacao($id);
        self::assertSame('EM_ANDAMENTO', $obrigacao['status']);
        self::assertNull($obrigacao['entregue_em']);
        self::assertNull($obrigacao['entregue_por']);
        self::assertSame('REC-ANTIGO', $obrigacao['protocolo']);
        self::assertStringContainsString('Retificar a guia', $admin->get('/obrigacoes/' . $id)->corpo);
    }

    public function testFormularioDaColaboradoraNaoOfereceReabertura(): void
    {
        $dispensada = $this->criarObrigacao(['status' => 'DISPENSADA']);
        $pendente = $this->criarObrigacao();
        $cliente = $this->entrar(self::COLABORADORA);

        $detalheDispensada = $cliente->get('/obrigacoes/' . $dispensada);
        self::assertStringNotContainsString('/obrigacoes/' . $dispensada . '/status', $detalheDispensada->corpo);

        $detalhePendente = $cliente->get('/obrigacoes/' . $pendente);
        self::assertStringContainsString('/obrigacoes/' . $pendente . '/status', $detalhePendente->corpo);
        self::assertStringContainsString('<option value="EM_ANDAMENTO">', $detalhePendente->corpo);
        self::assertStringContainsString('<option value="DISPENSADA">', $detalhePendente->corpo);
        self::assertStringNotContainsString('<option value="PENDENTE">', $detalhePendente->corpo);
    }

    public function testTransicaoProibidaResponde409(): void
    {
        $id = $this->criarObrigacao(['status' => 'AGUARDANDO_CLIENTE']);
        $cliente = $this->entrar(self::ADMIN);

        $resposta = $this->mudarStatus($cliente, $id, ['status' => 'PENDENTE', 'versao' => '1']);

        self::assertSame(409, $resposta->status);
        self::assertStringContainsString('Esta mudança de status não é permitida.', $resposta->corpo);
        self::assertSame('AGUARDANDO_CLIENTE', $this->obrigacao($id)['status']);
        self::assertSame(0, $this->contarAuditoria($id, 'status:PENDENTE'));
    }

    public function testVersaoDesatualizadaResponde409EPreservaODigitado(): void
    {
        $id = $this->criarObrigacao();
        $cliente = $this->entrar(self::COLABORADORA);
        $token = $this->tokenDaPagina($cliente, '/obrigacoes/' . $id);
        $this->banco()->prepare('UPDATE obrigacoes SET versao = 2 WHERE id = :id')->execute(['id' => $id]);

        $resposta = $cliente->post('/obrigacoes/' . $id . '/status', [
            '_csrf' => $token,
            'status' => 'DISPENSADA',
            'justificativa' => 'Empresa sem movimento',
            'versao' => '1',
        ]);

        self::assertSame(409, $resposta->status);
        self::assertStringContainsString('O registro foi alterado por outra operação.', $resposta->corpo);
        self::assertStringContainsString('Empresa sem movimento', $resposta->corpo);
        // O formulário volta com a versão atual, para a pessoa conferir e reenviar.
        self::assertStringContainsString('name="versao" value="2"', $resposta->corpo);
        self::assertSame('PENDENTE', $this->obrigacao($id)['status']);
        self::assertSame(0, $this->contarAuditoria($id, 'status:DISPENSADA'));
    }

    public function testPostSemCsrfResponde419(): void
    {
        $id = $this->criarObrigacao();
        $cliente = $this->entrar(self::ADMIN);

        $resposta = $cliente->post('/obrigacoes/' . $id . '/status', ['status' => 'EM_ANDAMENTO', 'versao' => '1']);

        self::assertSame(419, $resposta->status);
        self::assertSame('PENDENTE', $this->obrigacao($id)['status']);
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
        $token = $this->tokenDaPagina($cliente, '/obrigacoes');

        $resposta = $cliente->post('/obrigacoes/' . $id . '/status', ['_csrf' => $token, 'status' => 'EM_ANDAMENTO', 'versao' => '1']);

        self::assertSame(404, $resposta->status);
        self::assertSame('PENDENTE', $this->obrigacao($id)['status']);
        self::assertSame(0, $this->contarAuditoria($id, 'status:EM_ANDAMENTO'));
    }

    public function testClienteNaoMudaStatus(): void
    {
        $id = $this->criarObrigacao();
        $cliente = $this->entrar('cliente.padaria@exemplo.test');
        // O portal ainda não existe; a rota de apoio dos testes tem um formulário com CSRF.
        $token = $this->tokenDaPagina($cliente, '/_teste/protegida');

        $resposta = $cliente->post('/obrigacoes/' . $id . '/status', ['_csrf' => $token, 'status' => 'EM_ANDAMENTO', 'versao' => '1']);

        self::assertSame(403, $resposta->status);
        self::assertSame('PENDENTE', $this->obrigacao($id)['status']);
    }

    public function testJustificativaApareceEscapadaNoHistorico(): void
    {
        $id = $this->criarObrigacao();
        $cliente = $this->entrar(self::COLABORADORA);

        $resposta = $this->mudarStatus($cliente, $id, [
            'status' => 'DISPENSADA',
            'justificativa' => '<script>alert("x")</script>',
            'versao' => '1',
        ]);

        self::assertSame(302, $resposta->status);
        $detalhe = $cliente->get('/obrigacoes/' . $id);
        self::assertStringNotContainsString('<script>alert', $detalhe->corpo);
        self::assertStringContainsString('&lt;script&gt;alert', $detalhe->corpo);
    }

    /**
     * Abre o detalhe (para pegar o token CSRF) e envia o formulário de status.
     *
     * @param array<string, string> $dados
     */
    private function mudarStatus(ClienteHttp $cliente, int $id, array $dados): RespostaHttp
    {
        $dados['_csrf'] = $this->tokenDaPagina($cliente, '/obrigacoes/' . $id);

        return $cliente->post('/obrigacoes/' . $id . '/status', $dados);
    }

    private function tokenDaPagina(ClienteHttp $cliente, string $caminho): string
    {
        $pagina = $cliente->get($caminho);
        self::assertSame(200, $pagina->status);
        $token = ClienteHttp::extrairCsrf($pagina->corpo);
        self::assertNotNull($token);

        return $token;
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
     * @param array<string, mixed> $opcoes
     */
    private function criarObrigacao(array $opcoes = []): int
    {
        $escritorio = $this->escritorioId(self::ADMIN);
        $empresa = (new FabricaCadastro($this->banco()))->criarEmpresa(
            $escritorio,
            Cnpj::gerarFicticio(random_int(1, 99_999_999)),
            'Empresa FI04 ' . bin2hex(random_bytes(3))
        );
        $fiscal = new FabricaFiscal($this->banco());

        return $fiscal->criarObrigacao($escritorio, $empresa, $fiscal->criarTipo($escritorio), $this->usuarioId(self::ADMIN), $opcoes);
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
