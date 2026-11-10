<?php

declare(strict_types=1);

namespace App\Core;

use InvalidArgumentException;
use RuntimeException;

final class Response
{
    /**
     * @param array<string, string> $headers
     */
    private function __construct(
        public readonly string $corpo,
        public readonly int $status,
        public readonly array $headers
    ) {
    }

    public static function html(string $corpo, int $status = 200): self
    {
        self::validarStatus($status);

        return new self($corpo, $status, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public static function redirecionar(string $caminho): self
    {
        self::validarValorHeader($caminho);

        return new self('', 302, ['Location' => $caminho]);
    }

    public static function download(string $arquivo, string $nome, string $mime): self
    {
        self::validarValorHeader($nome);
        if (preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9!#$&^_.+-]*\/[a-zA-Z0-9][a-zA-Z0-9!#$&^_.+-]*\z/', $mime) !== 1) {
            throw new InvalidArgumentException('O tipo MIME do download é inválido.');
        }

        $conteudo = file_get_contents($arquivo);
        if ($conteudo === false) {
            throw new RuntimeException('Não foi possível ler o arquivo para download.');
        }

        $nome = self::nomeArquivo($nome);
        $nomeSeguro = preg_replace('/[^A-Za-z0-9._-]/', '_', $nome) ?: 'download';
        $disposicao = 'attachment; filename="' . $nomeSeguro . '"; filename*=UTF-8\'\''
            . rawurlencode($nome);

        return new self($conteudo, 200, [
            'Content-Type' => $mime,
            'Content-Length' => (string) strlen($conteudo),
            'Content-Disposition' => $disposicao,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function comHeader(string $nome, string $valor): self
    {
        if (preg_match('/\A[!#$%&\'*+.^_`|~0-9A-Za-z-]+\z/', $nome) !== 1) {
            throw new InvalidArgumentException('O nome do cabeçalho HTTP é inválido.');
        }
        self::validarValorHeader($valor);

        $headers = $this->headers;
        foreach (array_keys($headers) as $existente) {
            if (strcasecmp($existente, $nome) === 0) {
                unset($headers[$existente]);
            }
        }
        $headers[$nome] = $valor;

        return new self($this->corpo, $this->status, $headers);
    }

    public function enviar(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $nome => $valor) {
            header($nome . ': ' . $valor, true);
        }

        echo $this->corpo;
    }

    private static function validarStatus(int $status): void
    {
        if ($status < 100 || $status > 599) {
            throw new InvalidArgumentException('O status HTTP deve estar entre 100 e 599.');
        }
    }

    private static function validarValorHeader(string $valor): void
    {
        if (preg_match('/[\x00-\x08\x0A-\x1F\x7F]/', $valor) === 1) {
            throw new InvalidArgumentException('O valor do cabeçalho contém caracteres inválidos.');
        }
    }

    private static function nomeArquivo(string $nome): string
    {
        $nome = str_replace('\\', '/', $nome);
        $nome = basename($nome);
        if ($nome === '' || $nome === '.' || $nome === '..') {
            throw new InvalidArgumentException('O nome do arquivo para download é inválido.');
        }

        return $nome;
    }
}
