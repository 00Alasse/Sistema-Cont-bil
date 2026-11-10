<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Request;
use LogicException;
use PHPUnit\Framework\TestCase;

final class RequestTest extends TestCase
{
    /**
     * @var array<string, mixed>
     */
    private array $superglobaisOriginais = [];

    protected function tearDown(): void
    {
        foreach ($this->superglobaisOriginais as $nome => $valor) {
            $GLOBALS[$nome] = $valor;
        }

        parent::tearDown();
    }

    public function testCapturaRequisicaoRemovendoBasePathENormalizandoBarraFinal(): void
    {
        $this->definirSuperglobal('server', [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/tributo/empresas/7/?pagina=2',
            'REMOTE_ADDR' => '192.0.2.10',
        ]);
        $this->definirSuperglobal('get', ['pagina' => '2']);
        $this->definirSuperglobal('post', []);
        $this->definirSuperglobal('files', []);

        $request = Request::capturar('/tributo/');

        self::assertSame('GET', $request->metodo());
        self::assertSame('/empresas/7', $request->caminho());
        self::assertSame('2', $request->query('pagina'));
        self::assertSame('192.0.2.10', $request->ip());
    }

    public function testCapturaCaminhoSemBasePath(): void
    {
        $this->definirSuperglobal('server', [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/empresas/',
        ]);
        $this->definirSuperglobal('get', []);
        $this->definirSuperglobal('post', []);
        $this->definirSuperglobal('files', []);

        self::assertSame('/empresas', Request::capturar('')->caminho());
    }

    public function testNaoRemoveBasePathQuandoElaNaoEhSegmentoCompleto(): void
    {
        $this->definirSuperglobal('server', [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/tributo-outro/empresas',
        ]);
        $this->definirSuperglobal('get', []);
        $this->definirSuperglobal('post', []);
        $this->definirSuperglobal('files', []);

        self::assertSame('/tributo-outro/empresas', Request::capturar('/tributo')->caminho());
    }

    public function testEncapsulaQueryInputTodosArquivosEMetodo(): void
    {
        $arquivo = ['name' => 'documento.pdf', 'tmp_name' => 'temporario', 'error' => UPLOAD_ERR_OK];
        $request = new Request(
            'POST',
            '/documentos',
            ['pagina' => '3', 'compartilhado' => 'query'],
            ['nome' => 'Teste', 'compartilhado' => 'input'],
            ['arquivo' => $arquivo],
            '198.51.100.4'
        );

        self::assertSame('POST', $request->metodo());
        self::assertSame('3', $request->query('pagina'));
        self::assertSame('fallback', $request->query('inexistente', 'fallback'));
        self::assertSame('Teste', $request->input('nome'));
        self::assertSame('fallback', $request->input('inexistente', 'fallback'));
        self::assertSame([
            'pagina' => '3',
            'compartilhado' => 'input',
            'nome' => 'Teste',
        ], $request->todos());
        self::assertSame($arquivo, $request->arquivo('arquivo'));
        self::assertNull($request->arquivo('ausente'));
        self::assertSame('198.51.100.4', $request->ip());
    }

    public function testCaminhoRaizPermaneceComBarraUnica(): void
    {
        $this->definirSuperglobal('server', [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/tributo/',
        ]);
        $this->definirSuperglobal('get', []);
        $this->definirSuperglobal('post', []);
        $this->definirSuperglobal('files', []);

        self::assertSame('/', Request::capturar('/tributo')->caminho());
    }

    public function testUsuarioSemAssociacaoLancaExcecao(): void
    {
        $request = new Request('GET', '/');

        $this->expectException(LogicException::class);
        $request->usuario();
    }

    /**
     * @param array<string, mixed> $valor
     */
    private function definirSuperglobal(string $tipo, array $valor): void
    {
        $nome = match ($tipo) {
            'server' => '_SERVER',
            'get' => '_GET',
            'post' => '_POST',
            'files' => '_FILES',
            default => throw new LogicException('Superglobal de teste desconhecida.'),
        };
        $this->superglobaisOriginais[$nome] ??= $GLOBALS[$nome];
        $GLOBALS[$nome] = $valor;
    }
}
