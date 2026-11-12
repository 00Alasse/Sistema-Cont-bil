<?php

declare(strict_types=1);

$mensagemAviso = is_string($mensagem ?? null) ? trim($mensagem) : '';
$tituloAviso = is_string($tituloAviso ?? null) ? trim($tituloAviso) : '';
$urlAcao = is_string($urlAcao ?? null) ? $urlAcao : '';
$rotuloAcao = is_string($rotuloAcao ?? null) ? $rotuloAcao : '';
?>
<?php if ($mensagemAviso !== '') : ?>
    <aside class="aviso-gerencial" aria-label="Aviso">
        <?php if ($tituloAviso !== '') : ?>
            <h2><?= e($tituloAviso) ?></h2>
        <?php endif; ?>
        <p><?= e($mensagemAviso) ?></p>
        <?php if ($urlAcao !== '' && $rotuloAcao !== '') : ?>
            <a class="botao botao-secundario" href="<?= e(url($urlAcao)) ?>"><?= e($rotuloAcao) ?></a>
        <?php endif; ?>
    </aside>
<?php endif; ?>
