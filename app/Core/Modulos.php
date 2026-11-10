<?php

declare(strict_types=1);

namespace App\Core;

use InvalidArgumentException;
use RuntimeException;

final class Modulos
{
    private readonly string $diretorio;

    public function __construct(
        private readonly Router $router,
        ?string $diretorio = null
    ) {
        $this->diretorio = $diretorio ?? dirname(__DIR__, 2) . '/config/modules';
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function carregar(): array
    {
        if (!is_dir($this->diretorio)) {
            throw new RuntimeException('A pasta de configuração dos módulos não existe.');
        }

        $arquivos = glob($this->diretorio . '/*.php');
        if ($arquivos === false) {
            throw new RuntimeException('Não foi possível listar os módulos.');
        }
        sort($arquivos, SORT_STRING);

        $modulos = [];
        foreach ($arquivos as $arquivo) {
            $nome = pathinfo($arquivo, PATHINFO_FILENAME);
            $configuracao = require $arquivo;
            if (!is_array($configuracao)) {
                throw new RuntimeException('A configuração do módulo ' . $nome . ' deve retornar um array.');
            }

            $this->validarConfiguracao($nome, $configuracao);
            $this->registrarRotas($configuracao);
            $modulos[$nome] = $configuracao;
        }

        return $modulos;
    }

    /**
     * @param array<string, mixed> $configuracao
     */
    private function validarConfiguracao(string $nome, array $configuracao): void
    {
        $chavesObrigatorias = ['contratavel', 'menu', 'abas_empresa', 'cartoes_painel', 'rotas'];
        $chavesAusentes = array_diff($chavesObrigatorias, array_keys($configuracao));
        $chavesExtras = array_diff(array_keys($configuracao), $chavesObrigatorias);
        if ($chavesAusentes !== [] || $chavesExtras !== []) {
            throw new InvalidArgumentException('A configuração do módulo ' . $nome . ' possui chaves inválidas.');
        }

        $contratavel = $configuracao['contratavel'];
        if ($contratavel !== null && !in_array($contratavel, ['BPO_FINANCEIRO', 'CONSULTIVO'], true)) {
            throw new InvalidArgumentException('O código contratável do módulo ' . $nome . ' é inválido.');
        }

        foreach (['menu', 'abas_empresa', 'cartoes_painel', 'rotas'] as $chave) {
            if (!is_array($configuracao[$chave]) || !array_is_list($configuracao[$chave])) {
                throw new InvalidArgumentException('A configuração ' . $chave . ' do módulo ' . $nome . ' é inválida.');
            }
        }

        $this->validarMenus($nome, $configuracao['menu']);
        $this->validarAbas($nome, $configuracao['abas_empresa']);
        $this->validarCartoes($nome, $configuracao['cartoes_painel']);
        $this->validarRotas($nome, $configuracao['rotas']);
    }

    /**
     * @param list<mixed> $itens
     */
    private function validarMenus(string $nome, array $itens): void
    {
        foreach ($itens as $item) {
            if (
                !is_array($item)
                || !is_string($item['rotulo'] ?? null)
                || !is_string($item['url'] ?? null)
                || !is_string($item['icone'] ?? null)
                || !is_array($item['perfis'] ?? null)
                || !is_int($item['ordem'] ?? null)
            ) {
                throw new InvalidArgumentException('Um item de menu do módulo ' . $nome . ' é inválido.');
            }

            foreach ($item['perfis'] as $perfil) {
                if (!is_string($perfil)) {
                    throw new InvalidArgumentException('Os perfis de menu do módulo ' . $nome . ' são inválidos.');
                }
            }
        }
    }

    /**
     * @param list<mixed> $itens
     */
    private function validarAbas(string $nome, array $itens): void
    {
        foreach ($itens as $item) {
            if (
                !is_array($item)
                || !is_string($item['rotulo'] ?? null)
                || !is_string($item['url'] ?? null)
                || !is_int($item['ordem'] ?? null)
            ) {
                throw new InvalidArgumentException('Uma aba de empresa do módulo ' . $nome . ' é inválida.');
            }
        }
    }

    /**
     * @param list<mixed> $classes
     */
    private function validarCartoes(string $nome, array $classes): void
    {
        foreach ($classes as $classe) {
            if (
                !is_string($classe)
                || !class_exists($classe)
                || !is_a($classe, CartaoPainel::class, true)
            ) {
                throw new InvalidArgumentException('Um cartão do painel do módulo ' . $nome . ' é inválido.');
            }
        }
    }

    /**
     * @param list<mixed> $rotas
     */
    private function validarRotas(string $nome, array $rotas): void
    {
        foreach ($rotas as $rota) {
            if (
                !is_array($rota)
                || count($rota) !== 4
                || !is_string($rota[0] ?? null)
                || !is_string($rota[1] ?? null)
                || !is_array($rota[2] ?? null)
                || !array_is_list($rota[2])
                || count($rota[2]) !== 2
                || !is_string($rota[2][0])
                || !is_string($rota[2][1])
                || !is_array($rota[3] ?? null)
                || !array_is_list($rota[3])
            ) {
                throw new InvalidArgumentException('Uma rota do módulo ' . $nome . ' é inválida.');
            }

            foreach ($rota[3] as $middleware) {
                if (!is_string($middleware) || $middleware === '') {
                    throw new InvalidArgumentException('Um middleware do módulo ' . $nome . ' é inválido.');
                }
            }
        }
    }

    /**
     * @param array<string, mixed> $configuracao
     */
    private function registrarRotas(array $configuracao): void
    {
        foreach ($configuracao['rotas'] as [$metodo, $padrao, $handler, $middlewares]) {
            if ($configuracao['contratavel'] !== null) {
                $middlewares[] = 'modulo:' . $configuracao['contratavel'];
            }

            $this->router->adicionar($metodo, $padrao, $handler, $middlewares);
        }
    }
}
