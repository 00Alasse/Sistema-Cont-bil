<?php

declare(strict_types=1);

$filtrosAtivos = is_array($filtros ?? null) ? $filtros : [];
$filtrosVisiveis = [];
foreach ($filtrosAtivos as $rotuloFiltro => $valorFiltro) {
    if (
        is_string($rotuloFiltro)
        && (is_string($valorFiltro) || is_int($valorFiltro) || is_float($valorFiltro))
        && trim((string) $valorFiltro) !== ''
    ) {
        $filtrosVisiveis[$rotuloFiltro] = (string) $valorFiltro;
    }
}
?>
<?php if ($filtrosVisiveis !== []) : ?>
    <ul class="filtros-ativos" aria-label="Filtros aplicados">
        <?php foreach ($filtrosVisiveis as $rotuloFiltro => $valorFiltro) : ?>
            <li><span><?= e($rotuloFiltro) ?>:</span> <?= e($valorFiltro) ?></li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
