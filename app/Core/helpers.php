<?php

declare(strict_types=1);

use App\Core\Config;

function e(?string $valor): string
{
    return htmlspecialchars($valor ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * @param array<string, scalar|null|array<array-key, scalar|null>> $query
 */
function url(string $caminho, array $query = []): string
{
    $basePath = Config::obter('app.base_path', '');
    $basePath = is_string($basePath) ? trim($basePath, '/') : '';
    $caminho = preg_replace('#/+#', '/', $caminho) ?? '/';
    $caminho = '/' . ltrim($caminho, '/');

    $resultado = ($basePath === '' ? '' : '/' . $basePath)
        . ($caminho === '/' ? '/' : $caminho);
    if ($query !== []) {
        $resultado .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    return $resultado;
}

function csrf_campo(): string
{
    $metodo = ['App\\Core\\Csrf', 'token'];
    if (!is_callable($metodo)) {
        throw new LogicException('A integração de CSRF estará disponível após a tarefa F07.');
    }

    $token = call_user_func($metodo);
    if (!is_string($token)) {
        throw new UnexpectedValueException('O token CSRF deve ser uma string.');
    }

    return '<input type="hidden" name="_csrf" value="' . e($token) . '">';
}

/**
 * @param array<string, list<string>> $erros
 */
function erro_campo(array $erros, string $campo): string
{
    $mensagem = $erros[$campo][0] ?? null;
    if (!is_string($mensagem) || trim($mensagem) === '') {
        return '';
    }

    return '<span class="erro-campo">' . e($mensagem) . '</span>';
}

function dinheiro(int $centavos): string
{
    $metodo = ['App\\Support\\Dinheiro', 'formatar'];
    if (!is_callable($metodo)) {
        throw new LogicException('A formatação monetária estará disponível após a tarefa FN01.');
    }

    $valor = call_user_func($metodo, $centavos);
    if (!is_string($valor)) {
        throw new UnexpectedValueException('A formatação monetária deve retornar uma string.');
    }

    return $valor;
}

function data_br(?string $data): string
{
    if ($data === null || $data === '') {
        return '';
    }

    $dataFormatada = DateTimeImmutable::createFromFormat('!Y-m-d', $data);
    $erros = DateTimeImmutable::getLastErrors();
    if (
        $dataFormatada === false
        || ($erros !== false && ($erros['warning_count'] > 0 || $erros['error_count'] > 0))
        || $dataFormatada->format('Y-m-d') !== $data
    ) {
        return '';
    }

    return $dataFormatada->format('d/m/Y');
}
