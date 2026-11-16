<?php declare(strict_types=1); ?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($titulo ?? 'Entrar') ?> - -Tributo</title>
    <link rel="stylesheet" href="<?= e(url('/assets/css/app.css')) ?>">
</head>

<body>
    <main>
        <?= $conteudo ?? '' ?>
    </main>
</body>

</html>