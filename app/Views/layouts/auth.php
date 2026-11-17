<?php declare(strict_types=1); ?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0f2d57">
    <?php if (isset($redirecionarPara) && is_string($redirecionarPara) && $redirecionarPara !== '') : ?>
    <meta http-equiv="refresh" content="0; url=<?= e($redirecionarPara) ?>">
    <?php endif; ?>
    <title><?= e($titulo ?? 'Entrar') ?> - -Tributo</title>
    <link rel="icon" type="image/svg+xml" href="<?= e(url('/assets/img/favicon.svg')) ?>">
    <link rel="stylesheet" href="<?= e(url('/assets/vendor/bootstrap/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(url('/assets/css/app.css')) ?>">
</head>

<body class="acesso">
    <main class="pagina-acesso">
        <?= $conteudo ?? '' ?>
    </main>
    <script src="<?= e(url('/assets/js/app.js')) ?>" defer></script>
</body>

</html>
