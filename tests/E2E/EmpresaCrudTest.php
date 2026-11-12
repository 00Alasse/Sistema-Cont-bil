<?php

declare(strict_types=1);

namespace Tests\E2E;

use App\Support\Cnpj;
use Tests\Support\ClienteHttp;
use Tests\Support\FabricaCadastro;
use Tests\Support\RespostaHttp;

final class EmpresaCrudTest extends E2ETestCase
{
    private const SENHA = 'Senha@123';

    public function testCriaEmpresaComCnpjAlfanumericoNormalizadoEDetalhes(): void
    {
        $cliente = $this->entrar('admin@exemplo.test');
        $dados = $this->dadosFormulario('Empresa C01 alfanumérica', '12.ABC.345/01DE-35');
        $resposta = $this->postarFormulario($cliente, '/empresas/nova', '/empresas', $dados);

        self::assertSame(302, $resposta->status);
        $id = $this->idDaLocalizacao($resposta);
        $detalhes = $cliente->get('/empresas/' . $id);
        self::assertSame(200, $detalhes->status);
        self::assertStringContainsString('Empresa C01 alfanumérica', $detalhes->corpo);
        self::assertStringContainsString('Dados', $detalhes->corpo);
        self::assertSame('12ABC34501DE35', $this->cnpjSalvo($id));
        self::assertSame(1, $this->contarAuditoria($id, 'criar'));
    }

    public function testCamposOpcionaisPodemFicarAusentes(): void
    {
        $cliente = $this->entrar('admin@exemplo.test');
        $dados = [
            'razao_social' => 'Empresa com campos opcionais ausentes',
            'cnpj' => $this->novoCnpj(),
            'regime_tributario' => 'MEI',
        ];

        $resposta = $this->postarFormulario($cliente, '/empresas/nova', '/empresas', $dados);
        self::assertSame(302, $resposta->status);

        $detalhes = $cliente->get('/empresas/' . $this->idDaLocalizacao($resposta));
        self::assertSame(200, $detalhes->status);
        self::assertStringContainsString('Empresa com campos opcionais ausentes', $detalhes->corpo);
        self::assertStringContainsString('—', $detalhes->corpo);
    }

    public function testBuscaFiltraEPreservaFiltrosNaPaginacao(): void
    {
        $fabrica = new FabricaCadastro($this->banco());
        $escritorioId = $this->escritorioId('admin@exemplo.test');
        $responsavelId = $this->usuarioId('admin@exemplo.test');
        for ($indice = 1; $indice <= 21; $indice++) {
            $fabrica->criarEmpresa(
                $escritorioId,
                $this->novoCnpj(),
                sprintf('Lote C01 Empresa %02d', $indice),
                [
                    'responsavel_id' => $responsavelId,
                    'bpo_ativo' => 1,
                    'status' => 'ATIVA',
                ]
            );
        }

        $cliente = $this->entrar('admin@exemplo.test');
        $resposta = $cliente->get('/empresas?q=Lote%20C01&status=ATIVA&servico=bpo');

        self::assertSame(200, $resposta->status);
        self::assertStringContainsString('Lote C01 Empresa 01', $resposta->corpo);
        self::assertStringContainsString('pagina=2', $resposta->corpo);
        self::assertStringContainsString('q=Lote%20C01', $resposta->corpo);
        self::assertStringContainsString('status=ATIVA', $resposta->corpo);
        self::assertStringContainsString('servico=bpo', $resposta->corpo);
    }

    public function testAtualizaEmpresaERegistraAuditoria(): void
    {
        $id = $this->criarEmpresaDiretamente('Empresa antes da edição');
        $cliente = $this->entrar('admin@exemplo.test');
        $resposta = $cliente->get('/empresas/' . $id . '/editar');
        self::assertSame(200, $resposta->status);
        $token = ClienteHttp::extrairCsrf($resposta->corpo);
        self::assertNotNull($token);

        $dados = $this->dadosFormulario('Empresa depois da edição', $this->novoCnpj());
        $dados['versao'] = '1';
        $dados['_csrf'] = $token;
        $atualizacao = $cliente->post('/empresas/' . $id, $dados);

        self::assertSame(302, $atualizacao->status);
        self::assertSame('Empresa depois da edição', $this->razaoSocialSalva($id));
        self::assertSame(1, $this->contarAuditoria($id, 'editar'));
    }

    public function testAtualizacaoComCnpjJaUsadoRecebe422(): void
    {
        $id = $this->criarEmpresaDiretamente('Empresa original');
        $idComCnpjExistente = $this->criarEmpresaDiretamente('Empresa com CNPJ reservado');
        $cnpjOriginal = $this->cnpjSalvo($id);
        $cliente = $this->entrar('admin@exemplo.test');
        $pagina = $cliente->get('/empresas/' . $id . '/editar');
        $token = ClienteHttp::extrairCsrf($pagina->corpo);
        self::assertNotNull($token);

        $dados = $this->dadosFormulario(
            'Tentativa de duplicar CNPJ',
            $this->cnpjSalvo($idComCnpjExistente)
        );
        $dados['versao'] = '1';
        $dados['_csrf'] = $token;

        $resposta = $cliente->post('/empresas/' . $id, $dados);
        self::assertSame(422, $resposta->status);
        self::assertStringContainsString('cnpj', strtolower($resposta->corpo));
        self::assertSame($cnpjOriginal, $this->cnpjSalvo($id));
    }

    public function testVersaoDesatualizadaResponde409(): void
    {
        $id = $this->criarEmpresaDiretamente('Empresa com versão');
        $cliente = $this->entrar('admin@exemplo.test');
        $resposta = $cliente->get('/empresas/' . $id . '/editar');
        $token = ClienteHttp::extrairCsrf($resposta->corpo);
        self::assertNotNull($token);

        $stmt = $this->banco()->prepare(
            'UPDATE empresas SET versao = versao + 1 WHERE escritorio_id = :escritorio_id AND id = :id'
        );
        $stmt->execute(['escritorio_id' => $this->escritorioId('admin@exemplo.test'), 'id' => $id]);

        $dados = $this->dadosFormulario('Tentativa desatualizada', $this->novoCnpj());
        $dados['versao'] = '1';
        $dados['_csrf'] = $token;
        self::assertSame(409, $cliente->post('/empresas/' . $id, $dados)->status);
    }

    public function testValidacaoECnpjDuplicadoRespondem422(): void
    {
        $cliente = $this->entrar('admin@exemplo.test');
        $invalidos = $this->dadosFormulario('Empresa inválida', '00000000000000');
        $respostaInvalida = $this->postarFormulario(
            $cliente,
            '/empresas/nova',
            '/empresas',
            $invalidos
        );
        self::assertSame(422, $respostaInvalida->status);
        self::assertStringContainsString('cnpj', strtolower($respostaInvalida->corpo));

        $cnpj = $this->novoCnpj();
        $dados = $this->dadosFormulario('Primeira empresa', $cnpj);
        self::assertSame(302, $this->postarFormulario($cliente, '/empresas/nova', '/empresas', $dados)->status);

        $duplicado = $this->dadosFormulario('Segunda empresa', $cnpj);
        $respostaDuplicada = $this->postarFormulario(
            $cliente,
            '/empresas/nova',
            '/empresas',
            $duplicado
        );
        self::assertSame(422, $respostaDuplicada->status);
        self::assertStringContainsString('cnpj', strtolower($respostaDuplicada->corpo));
    }

    public function testRazaoSocialComHtmlEExibidaComEscape(): void
    {
        $cliente = $this->entrar('admin@exemplo.test');
        $dados = $this->dadosFormulario('<script>alert(1)</script>', $this->novoCnpj());
        $resposta = $this->postarFormulario($cliente, '/empresas/nova', '/empresas', $dados);
        self::assertSame(302, $resposta->status);

        $detalhes = $cliente->get('/empresas/' . $this->idDaLocalizacao($resposta));
        self::assertSame(200, $detalhes->status);
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $detalhes->corpo);
        self::assertStringNotContainsString('<script>alert(1)</script>', $detalhes->corpo);
    }

    public function testResponsavelDeOutroEscritorioRecebeErroDeValidacao(): void
    {
        $responsavelOutroEscritorio = $this->usuarioId('admin@modelo.test');
        $cliente = $this->entrar('admin@exemplo.test');
        $dados = $this->dadosFormulario('Empresa com responsável inválido', $this->novoCnpj());
        $dados['responsavel_id'] = (string) $responsavelOutroEscritorio;

        $resposta = $this->postarFormulario($cliente, '/empresas/nova', '/empresas', $dados);
        self::assertSame(422, $resposta->status);
        self::assertStringContainsString('responsavel_id', strtolower($resposta->corpo));
    }

    public function testColaboradorRecebe403AoExcluirEmpresa(): void
    {
        $id = $this->criarEmpresaDiretamente('Empresa protegida');
        $cliente = $this->entrar('ana@exemplo.test');
        $pagina = $cliente->get('/empresas/' . $id);
        $token = ClienteHttp::extrairCsrf($pagina->corpo);
        self::assertNotNull($token);

        $resposta = $cliente->post('/empresas/' . $id . '/excluir', ['_csrf' => $token]);

        self::assertSame(403, $resposta->status);
        self::assertSame('Empresa protegida', $this->razaoSocialSalva($id));
    }

    public function testEmpresaDeOutroEscritorioRetorna404(): void
    {
        $id = $this->criarEmpresaDiretamente('Empresa de outro escritório', 'admin@modelo.test');
        $cliente = $this->entrar('admin@exemplo.test');

        self::assertSame(404, $cliente->get('/empresas/' . $id)->status);
    }

    public function testExcluiEmpresaSemDependentesERegistraAuditoria(): void
    {
        $id = $this->criarEmpresaDiretamente('Empresa para excluir');
        $cliente = $this->entrar('admin@exemplo.test');
        $pagina = $cliente->get('/empresas/' . $id);
        $token = ClienteHttp::extrairCsrf($pagina->corpo);
        self::assertNotNull($token);

        $resposta = $cliente->post('/empresas/' . $id . '/excluir', ['_csrf' => $token]);

        self::assertSame(302, $resposta->status);
        self::assertSame(0, $this->contarEmpresa($id));
        self::assertSame(1, $this->contarAuditoria($id, 'excluir'));
    }

    public function testExclusaoComUsuarioDependenteResponde409(): void
    {
        $id = $this->criarEmpresaDiretamente('Empresa com dependente');
        $fabrica = new FabricaCadastro($this->banco());
        $fabrica->criarUsuario(
            $this->escritorioId('admin@exemplo.test'),
            'CLIENTE',
            $id
        );
        $cliente = $this->entrar('admin@exemplo.test');
        $pagina = $cliente->get('/empresas/' . $id);
        $token = ClienteHttp::extrairCsrf($pagina->corpo);
        self::assertNotNull($token);

        self::assertSame(
            409,
            $cliente->post('/empresas/' . $id . '/excluir', ['_csrf' => $token])->status
        );
        self::assertSame(1, $this->contarEmpresa($id));
    }

    private function entrar(string $email): ClienteHttp
    {
        $cliente = $this->cliente();
        $token = ClienteHttp::extrairCsrf($cliente->get('/login')->corpo);
        self::assertNotNull($token);
        $resposta = $cliente->post('/login', [
            '_csrf' => $token,
            'email' => $email,
            'senha' => self::SENHA,
        ]);
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
    private function dadosFormulario(string $razaoSocial, string $cnpj): array
    {
        return [
            'razao_social' => $razaoSocial,
            'nome_fantasia' => 'Fantasia C01',
            'cnpj' => $cnpj,
            'regime_tributario' => 'SIMPLES_NACIONAL',
            'segmento' => 'Serviços',
            'contato_nome' => 'Contato C01',
            'email' => 'contato@exemplo.test',
            'telefone' => '11999990000',
            'responsavel_id' => (string) $this->usuarioId('admin@exemplo.test'),
            'bpo_ativo' => '1',
            'consultivo_ativo' => '0',
            'observacoes' => 'Observação fictícia de teste',
        ];
    }

    private function criarEmpresaDiretamente(
        string $razaoSocial,
        string $emailResponsavel = 'admin@exemplo.test'
    ): int {
        return (new FabricaCadastro($this->banco()))->criarEmpresa(
            $this->escritorioId($emailResponsavel),
            $this->novoCnpj(),
            $razaoSocial,
            ['responsavel_id' => $this->usuarioId($emailResponsavel)]
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

    private function cnpjSalvo(int $id): string
    {
        $stmt = $this->banco()->prepare('SELECT cnpj FROM empresas WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return (string) $stmt->fetchColumn();
    }

    private function razaoSocialSalva(int $id): string
    {
        $stmt = $this->banco()->prepare('SELECT razao_social FROM empresas WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return (string) $stmt->fetchColumn();
    }

    private function contarEmpresa(int $id): int
    {
        $stmt = $this->banco()->prepare('SELECT COUNT(*) FROM empresas WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return (int) $stmt->fetchColumn();
    }

    private function contarAuditoria(int $id, string $acao): int
    {
        $stmt = $this->banco()->prepare(
            "SELECT COUNT(*) FROM logs_atividade
             WHERE entidade = 'empresa' AND entidade_id = :id AND acao = :acao"
        );
        $stmt->execute(['id' => $id, 'acao' => $acao]);

        return (int) $stmt->fetchColumn();
    }

    private function idDaLocalizacao(RespostaHttp $resposta): int
    {
        $localizacao = $resposta->location();
        self::assertNotNull($localizacao);
        $caminho = (string) parse_url($localizacao, PHP_URL_PATH);
        self::assertSame(1, preg_match('#\A/empresas/([0-9]+)\z#', $caminho, $partes));

        return (int) $partes[1];
    }

    private function novoCnpj(): string
    {
        return Cnpj::gerarFicticio(random_int(1, 99_999_999));
    }
}
