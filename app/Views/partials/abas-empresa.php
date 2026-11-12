<?php

declare(strict_types=1);

$idEmpresa = is_int($empresaId ?? null) && $empresaId > 0 ? $empresaId : null;
$abasEmpresa = is_array($abas ?? null) ? $abas : [];
?>
<?php if ($idEmpresa !== null && $abasEmpresa !== []) : ?>
    <nav class="abas-empresa" aria-label="Seções da empresa">
        <ul>
            <?php foreach ($abasEmpresa as $aba) : ?>
                <?php if (is_array($aba) && is_string($aba['rotulo'] ?? null) && is_string($aba['url'] ?? null)) : ?>
                    <?php $urlAba = str_replace('{id}', (string) $idEmpresa, $aba['url']); ?>
                    <li><a href="<?= e(url($urlAba)) ?>"><?= e($aba['rotulo']) ?></a></li>
                <?php endif; ?>
            <?php endforeach; ?>
        </ul>
    </nav>
<?php endif; ?>
