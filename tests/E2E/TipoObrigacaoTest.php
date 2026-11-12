<?php

declare(strict_types=1);

namespace Tests\E2E;

use App\Support\Cnpj;
use Tests\Support\ClienteHttp;
use Tests\Support\FabricaCadastro;
use Tests\Support\FabricaFiscal;
use Tests\Support\RespostaHttp;

final class TipoObrigacaoTest extends E2ETestCase
{
    private const SENHA = 'Senha@123';

    public function testAdminCriaTipoEOVeNaListaComAuditoria(): void
    {
        $nome = $this->nomeUnico('Guia E2E');
        $cliente = $this->entrar('admin@exemplo.test');

        $resposta = $this->postarFormulario($cliente, '/tipos-obrigacao/novo', '/tipos-obrigacao', $this->dadosFormulario($nome));

        self::assertSame(302, $resposta->status);
        self::assertStringEndsWith('/tipos-obrigacao', (string) $resposta->location());
        $id = $this->tipoId($nome);
        self::assertGreaterThan(0, $id);
        self::assertSame(1, $this->contarAuditoria('tipo_obrigacao', $id, 'criar'));

        $lista = $cliente->get('/tipos-obrigacao?q=' . rawurlencode($nome));
        self::assertSame(200, $lista->status);
        self::assertStringContainsString($nome, $lista->corpo);
        self::assertStringContainsString('confira o prazo vigente na fonte oficial', $lista->corpo);
    }

    public function testValidacaoRespondeComErrosPorCampo(): void
    {
        $cliente = $this->entrar('admin@exemplo.test');
        $dados = $this->dadosFormulario('');
        $dados['dia_vencimento'] = '';
        $dados['meses_apos_competencia'] = '13';

        $resposta = $this->postarFormulario($cliente, '/tipos-obrigacao/novo', '/tipos-obrigacao', $dados);

        self::assertSame(422, $resposta->status);
        self::assertStringContainsString('Nome é obrigatório', $resposta->corpo);
        self::assertStringContainsString('obrigatório para tipos mensais e trimestrais', $resposta->corpo);
        self::assertStringContainsString('deve ser no máximo 12', $resposta->corpo);
    }

    public function testNomeDuplicadoNoEscritorioResponde422(): void
    {
        $nome = $this->nomeUnico('Duplicado E2E');
        $this->criarTipoDiretamente($nome);
        $cliente = $this->entrar('admin@exemplo.test');

        $resposta = $this->postarFormulario(
            $cliente,
            '/tipos-obrigacao/novo',
            '/tipos-obrigacao',
            $this->dadosFormulario(mb_strtoupper($nome))
        );

        self::assertSame(422, $resposta->status);
        self::assertStringContainsString('Já existe um tipo com este nome', $resposta->corpo);
    }

    public function testAdminEditaTipoComAuditoria(): void
    {
        $id = $this->criarTipoDiretamente($this->nomeUnico('Antes E2E'));
        $novoNome = $this->nomeUnico('Depois E2E');
        $cliente = $this->entrar('admin@exemplo.test');
        $dados = $this->dadosFormulario($novoNome);
        $dados['periodicidade'] = 'ANUAL';
        $dados['dia_vencimento'] = '';
        $dados['ativo'] = '0';

        $resposta = $this->postarFormulario($cliente, '/tipos-obrigacao/' . $id . '/editar', '/tipos-obrigacao/' . $id, $dados);

        self::assertSame(302, $resposta->status);
        $tipo = $this->tipo($id);
        self::assertSame($novoNome, $tipo['nome']);
        self::assertSame('ANUAL', $tipo['periodicidade']);
        self::assertNull($tipo['dia_vencimento']);
        self::assertSame(0, (int) $tipo['ativo']);
        self::assertSame(1, $this->contarAuditoria('tipo_obrigacao', $id, 'editar'));
    }

    public function testExcluiTipoSemObrigacoesComAvisoDeCascata(): void
    {
        $id = $this->criarTipoDiretamente($this->nomeUnico('Excluir E2E'));
        $empresa = $this->criarEmpresaDiretamente();
        (new FabricaFiscal($this->banco()))->criarVinculo($this->escritorioId('admin@exemplo.test'), $empresa, $id);
        $cliente = $this->entrar('admin@exemplo.test');

        $edicao = $cliente->get('/tipos-obrigacao/' . $id . '/editar');
        self::assertSame(200, $edicao->status);
        self::assertStringContainsString('vínculos deste tipo com empresas também serão removidos', $edicao->corpo);

        $resposta = $this->postarFormulario($cliente, '/tipos-obrigacao/' . $id . '/editar', '/tipos-obrigacao/' . $id . '/excluir', []);

        self::assertSame(302, $resposta->status);
        self::assertSame([], $this->tipo($id));
        self::assertSame(0, $this->contarVinculos($empresa));
        self::assertSame(1, $this->contarAuditoria('tipo_obrigacao', $id, 'excluir'));
    }

    public function testExcluirTipoComObrigacoesResponde409(): void
    {
        $id = $this->criarTipoDiretamente($this->nomeUnico('Usado E2E'));
        $empresa = $this->criarEmpresaDiretamente();
        (new FabricaFiscal($this->banco()))->criarObrigacao(
            $this->escritorioId('admin@exemplo.test'),
            $empresa,
            $id,
            $this->usuarioId('admin@exemplo.test')
        );
        $cliente = $this->entrar('admin@exemplo.test');

        $resposta = $this->postarFormulario($cliente, '/tipos-obrigacao/' . $id . '/editar', '/tipos-obrigacao/' . $id . '/excluir', []);

        self::assertSame(409, $resposta->status);
        self::assertStringContainsString('inative o tipo', $resposta->corpo);
        self::assertNotSame([], $this->tipo($id));
    }

    public function testColaboradorListaEVinculaMasNaoMantemOCatalogo(): void
    {
        $id = $this->criarTipoDiretamente($this->nomeUnico('Colaborador E2E'));
        $cliente = $this->entrar('ana@exemplo.test');

        $lista = $cliente->get('/tipos-obrigacao');
        self::assertSame(200, $lista->status);
        self::assertStringContainsString('Tipos de obrigação', $lista->corpo);
        self::assertStringNotContainsString('/tipos-obrigacao/novo', $lista->corpo);

        self::assertSame(403, $cliente->get('/tipos-obrigacao/novo')->status);
        self::assertSame(403, $cliente->get('/tipos-obrigacao/' . $id . '/editar')->status);
        $token = ClienteHttp::extrairCsrf($lista->corpo);
        self::assertNotNull($token);
        $dados = $this->dadosFormulario($this->nomeUnico('Proibido'));
        $dados['_csrf'] = $token;
        self::assertSame(403, $cliente->post('/tipos-obrigacao', $dados)->status);
        self::assertSame(403, $cliente->post('/tipos-obrigacao/' . $id . '/excluir', ['_csrf' => $token])->status);
        self::assertNotSame([], $this->tipo($id));
    }

    public function testClienteRecebe403EVisitanteVaiParaOLogin(): void
    {
        $cliente = $this->entrar('cliente.padaria@exemplo.test');
        self::assertSame(403, $cliente->get('/tipos-obrigacao')->status);
        self::assertSame(403, $cliente->get('/empresas/1/tipos-obrigacao')->status);

        $visitante = $this->cliente()->get('/tipos-obrigacao');
        self::assertSame(302, $visitante->status);
        self::assertStringContainsString('/login', (string) $visitante->location());
    }

    public function testTipoDeOutroEscritorioResponde404(): void
    {
        $id = $this->criarTipoDiretamente($this->nomeUnico('Modelo E2E'), 'admin@modelo.test');
        $cliente = $this->entrar('admin@exemplo.test');

        self::assertSame(404, $cliente->get('/tipos-obrigacao/' . $id . '/editar')->status);
        $token = ClienteHttp::extrairCsrf($cliente->get('/tipos-obrigacao')->corpo);
        self::assertNotNull($token);
        $dados = $this->dadosFormulario('Invasão');
        $dados['_csrf'] = $token;
        self::assertSame(404, $cliente->post('/tipos-obrigacao/' . $id, $dados)->status);
        self::assertSame(404, $cliente->post('/tipos-obrigacao/' . $id . '/excluir', ['_csrf' => $token])->status);
        self::assertNotSame('Invasão', $this->tipo($id)['nome'] ?? null);
    }

    public function testPostSemCsrfResponde419(): void
    {
        $cliente = $this->entrar('admin@exemplo.test');

        self::assertSame(419, $cliente->post('/tipos-obrigacao', $this->dadosFormulario($this->nomeUnico('Sem CSRF')))->status);
    }

    public function testNomeComHtmlEhEscapado(): void
    {
        $nome = '<script>alert(' . random_int(1, 999_999) . ')</script>';
        $this->criarTipoDiretamente($nome);
        $cliente = $this->entrar('admin@exemplo.test');

        $resposta = $cliente->get('/tipos-obrigacao?q=' . rawurlencode($nome));

        // Mesmo escape do helper e() das views.
        self::assertStringContainsString(
            htmlspecialchars($nome, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            $resposta->corpo
        );
        self::assertStringNotContainsString($nome, $resposta->corpo);
    }

    public function testFiltrosSaoPreservadosNaPaginacao(): void
    {
        $prefixo = $this->nomeUnico('Lote');
        for ($indice = 1; $indice <= 21; $indice++) {
            $this->criarTipoDiretamente(sprintf('%s %02d', $prefixo, $indice));
        }
        $cliente = $this->entrar('admin@exemplo.test');

        $resposta = $cliente->get('/tipos-obrigacao?q=' . rawurlencode($prefixo) . '&esfera=FEDERAL&ativo=1');

        self::assertSame(200, $resposta->status);
        self::assertStringContainsString($prefixo . ' 01', $resposta->corpo);
        self::assertStringNotContainsString($prefixo . ' 21', $resposta->corpo);
        self::assertStringContainsString('pagina=2', $resposta->corpo);
        self::assertStringContainsString('esfera=FEDERAL', $resposta->corpo);
        self::assertStringContainsString('q=' . rawurlencode($prefixo), $resposta->corpo);
    }

    public function testAbaObrigacoesEVinculoPeloColaborador(): void
    {
        $empresa = $this->criarEmpresaDiretamente();
        $tipoA = $this->criarTipoDiretamente($this->nomeUnico('Vinculo A'));
        $tipoB = $this->criarTipoDiretamente($this->nomeUnico('Vinculo B'));
        $ana = $this->usuarioId('ana@exemplo.test');
        $cliente = $this->entrar('ana@exemplo.test');

        $detalhes = $cliente->get('/empresas/' . $empresa);
        self::assertStringContainsString('/empresas/' . $empresa . '/tipos-obrigacao', $detalhes->corpo);

        $resposta = $this->postarFormulario(
            $cliente,
            '/empresas/' . $empresa . '/tipos-obrigacao',
            '/empresas/' . $empresa . '/tipos-obrigacao',
            [
                'tipos[0]' => (string) $tipoA,
                'tipos[1]' => (string) $tipoB,
                'responsavel_padrao[' . $tipoA . ']' => (string) $ana,
                'responsavel_padrao[' . $tipoB . ']' => '',
            ]
        );

        self::assertSame(302, $resposta->status);
        self::assertStringEndsWith('/empresas/' . $empresa . '/tipos-obrigacao', (string) $resposta->location());
        self::assertSame([$tipoA => $ana, $tipoB => null], $this->vinculos($empresa));
        self::assertSame(1, $this->contarAuditoria('empresa', $empresa, 'tipos_obrigacao'));

        $pagina = $cliente->get('/empresas/' . $empresa . '/tipos-obrigacao');
        self::assertSame(200, $pagina->status);
        self::assertStringContainsString('class="abas-empresa"', $pagina->corpo);
        self::assertMatchesRegularExpression('/value="' . $tipoA . '"[^>]*checked/', $pagina->corpo);

        // Desmarcar tudo remove os vínculos.
        $vazio = $this->postarFormulario(
            $cliente,
            '/empresas/' . $empresa . '/tipos-obrigacao',
            '/empresas/' . $empresa . '/tipos-obrigacao',
            []
        );
        self::assertSame(302, $vazio->status);
        self::assertSame([], $this->vinculos($empresa));
    }

    public function testVinculoComTipoOuResponsavelDeOutroEscritorioResponde422(): void
    {
        $empresa = $this->criarEmpresaDiretamente();
        $tipo = $this->criarTipoDiretamente($this->nomeUnico('Valido'));
        $tipoModelo = $this->criarTipoDiretamente($this->nomeUnico('Do Modelo'), 'admin@modelo.test');
        $cliente = $this->entrar('admin@exemplo.test');
        $caminho = '/empresas/' . $empresa . '/tipos-obrigacao';

        $tipoInvalido = $this->postarFormulario($cliente, $caminho, $caminho, ['tipos[0]' => (string) $tipoModelo]);
        self::assertSame(422, $tipoInvalido->status);
        self::assertStringContainsString('Selecione apenas tipos ativos deste escritório', $tipoInvalido->corpo);

        $responsavelInvalido = $this->postarFormulario($cliente, $caminho, $caminho, [
            'tipos[0]' => (string) $tipo,
            'responsavel_padrao[' . $tipo . ']' => (string) $this->usuarioId('admin@modelo.test'),
        ]);
        self::assertSame(422, $responsavelInvalido->status);
        self::assertStringContainsString('responsável ativo deste escritório', $responsavelInvalido->corpo);

        self::assertSame([], $this->vinculos($empresa));
    }

    public function testVinculoDeEmpresaDeOutroEscritorioResponde404(): void
    {
        $empresa = $this->criarEmpresaDiretamente('admin@modelo.test');
        $cliente = $this->entrar('admin@exemplo.test');

        self::assertSame(404, $cliente->get('/empresas/' . $empresa . '/tipos-obrigacao')->status);
        $token = ClienteHttp::extrairCsrf($cliente->get('/tipos-obrigacao')->corpo);
        self::assertNotNull($token);
        self::assertSame(
            404,
            $cliente->post('/empresas/' . $empresa . '/tipos-obrigacao', ['_csrf' => $token])->status
        );
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

    /**
     * @return array<string, string>
     */
    private function dadosFormulario(string $nome): array
    {
        return [
            'nome' => $nome,
            'descricao' => 'Descrição fictícia de teste',
            'esfera' => 'FEDERAL',
            'periodicidade' => 'MENSAL',
            'dia_vencimento' => '20',
            'meses_apos_competencia' => '1',
            'ativo' => '1',
        ];
    }

    private function nomeUnico(string $prefixo): string
    {
        return $prefixo . ' ' . bin2hex(random_bytes(4));
    }

    private function criarTipoDiretamente(string $nome, string $email = 'admin@exemplo.test'): int
    {
        return (new FabricaFiscal($this->banco()))->criarTipo($this->escritorioId($email), [
            'nome' => $nome,
            'esfera' => 'FEDERAL',
        ]);
    }

    private function criarEmpresaDiretamente(string $email = 'admin@exemplo.test'): int
    {
        return (new FabricaCadastro($this->banco()))->criarEmpresa(
            $this->escritorioId($email),
            Cnpj::gerarFicticio(random_int(1, 99_999_999)),
            'Empresa FI02 ' . bin2hex(random_bytes(3)),
            ['responsavel_id' => $this->usuarioId($email)]
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

    private function tipoId(string $nome): int
    {
        $stmt = $this->banco()->prepare('SELECT id FROM tipos_obrigacao WHERE nome = :nome');
        $stmt->execute(['nome' => $nome]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return array<string, mixed>
     */
    private function tipo(int $id): array
    {
        $stmt = $this->banco()->prepare('SELECT * FROM tipos_obrigacao WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $tipo = $stmt->fetch();

        return is_array($tipo) ? $tipo : [];
    }

    /**
     * @return array<int, int|null>
     */
    private function vinculos(int $empresaId): array
    {
        $stmt = $this->banco()->prepare(
            'SELECT tipo_obrigacao_id, responsavel_padrao_id FROM empresa_tipos_obrigacao
             WHERE empresa_id = :empresa ORDER BY tipo_obrigacao_id'
        );
        $stmt->execute(['empresa' => $empresaId]);
        $vinculos = [];
        foreach ($stmt->fetchAll() as $linha) {
            $vinculos[(int) $linha['tipo_obrigacao_id']] = $linha['responsavel_padrao_id'] === null
                ? null
                : (int) $linha['responsavel_padrao_id'];
        }

        return $vinculos;
    }

    private function contarVinculos(int $empresaId): int
    {
        return count($this->vinculos($empresaId));
    }

    private function contarAuditoria(string $entidade, int $id, string $acao): int
    {
        $stmt = $this->banco()->prepare(
            'SELECT COUNT(*) FROM logs_atividade WHERE entidade = :entidade AND entidade_id = :id AND acao = :acao'
        );
        $stmt->execute(['entidade' => $entidade, 'id' => $id, 'acao' => $acao]);

        return (int) $stmt->fetchColumn();
    }
}
