<?php

declare(strict_types=1);

namespace App\Core;

use LogicException;

final class Request
{
    /**
     * @param array<string, mixed> $queryDados
     * @param array<string, mixed> $inputDados
     * @param array<string, array<string, mixed>> $arquivos
     */
    public function __construct(
        private readonly string $metodoHttp,
        private readonly string $uri,
        private readonly array $queryDados = [],
        private readonly array $inputDados = [],
        private readonly array $arquivos = [],
        private readonly ?string $enderecoIp = null,
        private readonly ?UsuarioLogado $usuarioAssociado = null
    ) {
    }

    public static function capturar(string $basePath): self
    {
        $server = $_SERVER;
        $uri = (string) ($server['REQUEST_URI'] ?? '/');
        $caminho = parse_url($uri, PHP_URL_PATH);
        $caminho = is_string($caminho) ? rawurldecode($caminho) : '/';

        return new self(
            strtoupper((string) ($server['REQUEST_METHOD'] ?? 'GET')),
            self::removerBasePath(self::normalizarCaminho($caminho), $basePath),
            $_GET,
            $_POST,
            $_FILES,
            isset($server['REMOTE_ADDR']) ? (string) $server['REMOTE_ADDR'] : null
        );
    }

    public function metodo(): string
    {
        return strtoupper($this->metodoHttp);
    }

    public function caminho(): string
    {
        return self::normalizarCaminho($this->uri);
    }

    public function query(string $chave, mixed $padrao = null): mixed
    {
        return $this->queryDados[$chave] ?? $padrao;
    }

    public function input(string $chave, mixed $padrao = null): mixed
    {
        return $this->inputDados[$chave] ?? $padrao;
    }

    /**
     * @return array<string, mixed>
     */
    public function todos(): array
    {
        return array_merge($this->queryDados, $this->inputDados);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function arquivo(string $chave): ?array
    {
        $arquivo = $this->arquivos[$chave] ?? null;

        return is_array($arquivo) ? $arquivo : null;
    }

    public function ip(): ?string
    {
        return $this->enderecoIp;
    }

    public function usuario(): UsuarioLogado
    {
        if ($this->usuarioAssociado === null) {
            throw new LogicException('A requisição ainda não tem um usuário associado.');
        }

        return $this->usuarioAssociado;
    }

    public function comUsuario(UsuarioLogado $usuario): self
    {
        return new self(
            $this->metodoHttp,
            $this->uri,
            $this->queryDados,
            $this->inputDados,
            $this->arquivos,
            $this->enderecoIp,
            $usuario
        );
    }

    private static function normalizarCaminho(string $caminho): string
    {
        $caminho = preg_replace('#/+#', '/', $caminho) ?? '/';
        $caminho = '/' . ltrim($caminho, '/');

        if ($caminho !== '/') {
            $caminho = rtrim($caminho, '/');
        }

        return $caminho === '' ? '/' : $caminho;
    }

    private static function removerBasePath(string $caminho, string $basePath): string
    {
        $basePath = self::normalizarCaminho($basePath);
        if ($basePath === '/') {
            return $caminho;
        }

        if ($caminho === $basePath) {
            return '/';
        }

        if (str_starts_with($caminho, $basePath . '/')) {
            return self::normalizarCaminho(substr($caminho, strlen($basePath)));
        }

        return $caminho;
    }
}
