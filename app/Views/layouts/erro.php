<?php declare(strict_types=1); ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0f2d57">
    <title><?= e($titulo ?? '-Tributo') ?> - -Tributo</title>
    <link rel="icon" type="image/svg+xml" href="<?= e(url('/assets/img/favicon.svg')) ?>">
    <link rel="stylesheet" href="<?= e(url('/assets/css/app.css')) ?>">
</head>
<body class="acesso">
    <main class="pagina-acesso">
        <?= $conteudo ?? '' ?>
    </main>
</body>
</html>
