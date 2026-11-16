<?php

declare(strict_types=1);

/*
 * Selo de status (badge). O sentido nunca depende só da cor: o texto sempre diz o estado.
 *
 * Variáveis do chamador:
 *   $seloRotulo  texto exibido (obrigatório; sem ele nada é impresso).
 *   $seloTom     "sucesso", "aviso", "perigo", "info" ou "neutro" (padrão).
 */
$rotuloSelo = is_string($seloRotulo ?? null) ? trim($seloRotulo) : '';
$tomSelo = is_string($seloTom ?? null) && in_array($seloTom, ['sucesso', 'aviso', 'perigo', 'info', 'neutro'], true)
    ? $seloTom
    : 'neutro';
unset($seloRotulo, $seloTom);
?>
<?php if ($rotuloSelo !== '') : ?>
    <span class="selo selo-<?= e($tomSelo) ?>"><?= e($rotuloSelo) ?></span>
<?php endif; ?>
