<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Response;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ResponseTest extends TestCase
{
    public function testCriaRespostaHtmlComStatusEContentType(): void
    {
        $response = Response::html('<h1>Pronto</h1>', 201);

        self::assertSame('<h1>Pronto</h1>', $response->corpo);
        self::assertSame(201, $response->status);
        self::assertSame('text/html; charset=UTF-8', $response->headers['Content-Type']);
    }

    public function testCriaRedirecionamentoComLocalizacao(): void
    {
        $response = Response::redirecionar('/empresas');

        self::assertSame('', $response->corpo);
        self::assertSame(302, $response->status);
        self::assertSame('/empresas', $response->headers['Location']);
    }

    public function testRejeitaQuebraDeLinhaNaLocalizacao(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Response::redirecionar("/seguro\r\nX-Injected: yes");
    }

    public function testCriaDownloadComConteudoEHeadersSeguros(): void
    {
        $arquivo = tempnam(sys_get_temp_dir(), 'tributo-download-');
        self::assertNotFalse($arquivo);
        self::assertNotFalse(file_put_contents($arquivo, 'conteudo'));

        try {
            $response = Response::download($arquivo, 'relatório final.pdf', 'application/pdf');

            self::assertSame('conteudo', $response->corpo);
            self::assertSame(200, $response->status);
            self::assertSame('application/pdf', $response->headers['Content-Type']);
            self::assertSame('8', $response->headers['Content-Length']);
            self::assertStringContainsString('attachment', $response->headers['Content-Disposition']);
            self::assertStringContainsString('filename*=UTF-8\'\'', $response->headers['Content-Disposition']);
        } finally {
            unlink($arquivo);
        }
    }

    public function testDownloadRejeitaHeaderComQuebraDeLinha(): void
    {
        $arquivo = tempnam(sys_get_temp_dir(), 'tributo-download-');
        self::assertNotFalse($arquivo);

        try {
            $this->expectException(InvalidArgumentException::class);
            Response::download($arquivo, "arquivo.pdf\r\nX-Test: sim", 'application/pdf');
        } finally {
            unlink($arquivo);
        }
    }

    public function testComHeaderRetornaNovaRespostaEPreservaStatusEHeaders(): void
    {
        $original = Response::html('conteudo', 202);
        $response = $original->comHeader('X-Request-Id', 'abc-123');

        self::assertNotSame($original, $response);
        self::assertSame(202, $response->status);
        self::assertSame('conteudo', $response->corpo);
        self::assertSame('text/html; charset=UTF-8', $response->headers['Content-Type']);
        self::assertSame('abc-123', $response->headers['X-Request-Id']);
        self::assertArrayNotHasKey('X-Request-Id', $original->headers);
    }

    public function testComHeaderRejeitaNomeOuValorInseguro(): void
    {
        $response = Response::html('');

        $this->expectException(InvalidArgumentException::class);
        $response->comHeader('X-Test', "valor\r\nX-Injected: sim");
    }

    public function testEnviarEmiteStatusHeadersECorpo(): void
    {
        $statusAnterior = http_response_code();
        $response = Response::html('resposta enviada', 202)->comHeader('X-Test', 'preservado');
        ob_start();

        try {
            $response->enviar();
            $corpo = ob_get_contents();
        } finally {
            ob_end_clean();
            if ($statusAnterior !== false) {
                http_response_code($statusAnterior);
            }
        }

        self::assertSame('resposta enviada', $corpo);
        self::assertSame(202, $response->status);
        self::assertSame('preservado', $response->headers['X-Test']);
    }
}
