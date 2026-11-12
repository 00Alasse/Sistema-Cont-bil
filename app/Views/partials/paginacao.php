<?php

declare(strict_types=1);

$paginaAtual = is_int($pagina ?? null) ? max(1, $pagina) : 1;
$totalPaginas = is_int($totalPaginas ?? null) ? max(1, $totalPaginas) : 1;
$caminho = is_string($caminho ?? null) ? $caminho : '/';
$parametrosRecebidos = is_array($queryAtual ?? null) ? $queryAtual : [];
$parametros = [];
foreach ($parametrosRecebidos as $chave => $valor) {
    if (!is_string($chave) || $chave === 'pagina') {
        continue;
    }
    if (is_scalar($valor) || $valor === null) {
        $parametros[$chave] = $valor;
        continue;
    }
    if (is_array($valor)) {
        $lista = [];
        foreach ($valor as $indice => $item) {
            if (is_scalar($item) || $item === null) {
                $lista[$indice] = $item;
            }
        }
        $parametros[$chave] = $lista;
    }
}
?>
<?php if ($totalPaginas > 1) : ?>
    <nav class="paginacao" aria-label="Paginação">
        <span>Página <?= e((string) $paginaAtual) ?> de <?= e((string) $totalPaginas) ?></span>
        <ul>
            <?php if ($paginaAtual > 1) : ?>
                <?php $parametros['pagina'] = $paginaAtual - 1; ?>
                <li><a href="<?= e(url($caminho, $parametros)) ?>">Anterior</a></li>
            <?php endif; ?>
            <?php if ($paginaAtual < $totalPaginas) : ?>
                <?php $parametros['pagina'] = $paginaAtual + 1; ?>
                <li><a href="<?= e(url($caminho, $parametros)) ?>">Próxima</a></li>
            <?php endif; ?>
        </ul>
    </nav>
<?php endif; ?>
