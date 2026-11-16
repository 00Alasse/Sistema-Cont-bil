<?php

declare(strict_types=1);

/*
 * Menu principal. Funciona sem JavaScript: são links comuns. O destaque da página atual
 * (aria-current) é melhoria progressiva feita pelo app.js.
 *
 * Variável do chamador: $itensMenu, lista de ['rotulo', 'url', 'icone'] já filtrada por perfil e módulo.
 */
$itensNavegacao = is_array($itensMenu ?? null) ? $itensMenu : [];
$iconesDoMenu = ['painel', 'empresa', 'obrigacoes', 'tipos', 'usuarios', 'portal'];
?>
<nav class="navegacao-principal" aria-label="Navegação principal">
    <ul>
        <?php foreach ($itensNavegacao as $itemNavegacao) : ?>
            <li>
                <a class="menu-item" href="<?= e(url($itemNavegacao['url'])) ?>" title="<?= e($itemNavegacao['rotulo']) ?>">
                    <span class="menu-icone" aria-hidden="true"><?php
                        $icone = in_array($itemNavegacao['icone'], $iconesDoMenu, true) ? $itemNavegacao['icone'] : 'padrao';
                        require __DIR__ . '/icone.php';
                    ?></span>
                    <span class="menu-rotulo"><?= e($itemNavegacao['rotulo']) ?></span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</nav>
