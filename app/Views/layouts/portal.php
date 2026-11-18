<?php

declare(strict_types=1);

/*
 * Layout do portal do cliente (ADR-024). Mesma estrutura do escritório; muda só o destino da marca,
 * o texto do cabeçalho e a classe do corpo.
 */
$tituloPagina = is_string($titulo ?? null) ? $titulo : 'Portal do cliente';
$usuarioAtual = $usuario ?? null;
$conteudoPagina = is_string($conteudo ?? null) ? $conteudo : '';
/** @var list<array{rotulo: string, url: string, icone: string, ordem: int}> $itensMenu */
$itensMenu = is_array($itensMenu ?? null) ? $itensMenu : [];
$marcaUrl = '/portal';
$contextoCabecalho = 'Portal do cliente';
$classeCorpo = 'aplicacao portal';
require __DIR__ . '/../partials/estrutura-aplicacao.php';
