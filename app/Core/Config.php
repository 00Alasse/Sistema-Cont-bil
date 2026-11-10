<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class Config
{
    /**
     * @var array<string, mixed>|null
     */
    private static ?array $configuracoes = null;

    public static function obter(string $chave, mixed $padrao = null): mixed
    {
        self::carregarConfiguracoes();

        $valor = self::$configuracoes;
        foreach (explode('.', $chave) as $parte) {
            if (!is_array($valor) || !array_key_exists($parte, $valor)) {
                return $padrao;
            }

            $valor = $valor[$parte];
        }

        return $valor;
    }

    /**
     * @return array<string, mixed>
     */
    public static function regras(string $nome): array
    {
        if (preg_match('/\A[a-zA-Z0-9_-]+\z/', $nome) !== 1) {
            throw new RuntimeException('Nome de arquivo de regras inválido.');
        }

        $arquivo = dirname(__DIR__, 2) . '/config/regras/' . $nome . '.php';
        if (!is_file($arquivo)) {
            throw new RuntimeException('O arquivo de regras solicitado não existe.');
        }

        $regras = require $arquivo;
        if (!is_array($regras)) {
            throw new RuntimeException('O arquivo de regras deve retornar um array.');
        }

        return $regras;
    }

    /**
     * Define as configurações em testes. Sem chamada, a configuração padrão é carregada.
     *
     * @param array<string, mixed> $configuracoes
     */
    public static function definir(array $configuracoes): void
    {
        self::$configuracoes = $configuracoes;
    }

    private static function carregarConfiguracoes(): void
    {
        if (self::$configuracoes !== null) {
            return;
        }

        $arquivoEnv = dirname(__DIR__, 2) . '/.env';
        Env::carregar($arquivoEnv);

        $arquivoConfig = dirname(__DIR__, 2) . '/config/app.php';
        $configuracoes = require $arquivoConfig;
        if (!is_array($configuracoes)) {
            throw new RuntimeException('A configuração da aplicação deve retornar um array.');
        }

        self::$configuracoes = $configuracoes;
    }
}
