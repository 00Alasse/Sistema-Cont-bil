<?php

declare(strict_types=1);

/*
 * Marca do produto: monograma (um traço e um "T") e o nome oficial, -Tributo, sempre como texto
 * (sem exclamação; ver AGENTS §4). É o único lugar que desenha a marca nas telas.
 *
 * Variáveis do chamador:
 *   $marcaUrl       destino do link (caminho sem o prefixo da aplicação); sem ele, não há link.
 *   $marcaVariante  "escura" (padrão: fundo escuro, como na barra lateral) ou "clara" (fundo claro).
 */
$destinoMarca = is_string($marcaUrl ?? null) && $marcaUrl !== '' ? $marcaUrl : null;
$classeMarca = ($marcaVariante ?? 'escura') === 'clara' ? 'marca marca-sobre-claro' : 'marca';
unset($marcaUrl, $marcaVariante);

$simboloMarca = '<svg class="marca-simbolo" xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 32 32" aria-hidden="true" focusable="false">'
    . '<rect class="marca-fundo" width="32" height="32" rx="8"/>'
    . '<rect class="marca-traco" x="4" y="15" width="6" height="3" rx="1.5"/>'
    . '<path class="marca-t" d="M12 9h16v4h-6v12h-4V13h-6z"/>'
    . '</svg>';
?>
<?php if ($destinoMarca !== null) : ?>
    <a class="<?= $classeMarca ?>" href="<?= e(url($destinoMarca)) ?>"><?= $simboloMarca ?><span class="marca-nome">-Tributo</span></a>
<?php else : ?>
    <span class="<?= $classeMarca ?>"><?= $simboloMarca ?><span class="marca-nome">-Tributo</span></span>
<?php endif; ?>
