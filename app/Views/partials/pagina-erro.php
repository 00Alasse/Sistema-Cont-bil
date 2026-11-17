<?php

declare(strict_types=1);

/*
 * Corpo comum das páginas de erro (layout "erro"): marca, ícone, título, mensagem e um caminho de volta.
 *
 * Variáveis do chamador: $erroTitulo e $erroMensagem (obrigatórias), $erroIcone (nome do ícone),
 * $erroTom ("aviso" ou "perigo"), $erroDetalhe (texto extra opcional).
 */
$tituloErro = is_string($erroTitulo ?? null) ? $erroTitulo : 'Erro';
$mensagemErro = is_string($erroMensagem ?? null) ? $erroMensagem : '';
$detalheErro = is_string($erroDetalhe ?? null) ? $erroDetalhe : '';
$tomErro = ($erroTom ?? 'aviso') === 'perigo' ? 'perigo' : 'aviso';
$iconeErro = is_string($erroIcone ?? null) ? $erroIcone : 'aviso';
?>
<section class="cartao-acesso pagina-erro">
    <?php $marcaVariante = 'clara'; require __DIR__ . '/marca.php'; ?>
    <span class="erro-icone erro-icone-<?= e($tomErro) ?>">
        <?php $icone = $iconeErro; require __DIR__ . '/icone.php'; ?>
    </span>
    <h1><?= e($tituloErro) ?></h1>
    <p><?= e($mensagemErro) ?></p>
    <?php if ($detalheErro !== '') : ?>
        <p class="painel-nota"><?= e($detalheErro) ?></p>
    <?php endif; ?>
    <a class="btn btn-primary" href="<?= e(url('/')) ?>">Ir para a página inicial</a>
</section>