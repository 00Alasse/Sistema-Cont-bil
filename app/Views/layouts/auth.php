<?php declare(strict_types=1); ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#153e75">
    <title><?= e($titulo ?? 'Entrar') ?> - -Tributo!</title>
    <link rel="stylesheet" href="<?= e(url('/assets/css/app.css')) ?>">
</head>
<body class="corpo-auth">
    <main class="pagina-auth">
        <div class="cartao cartao-auth">
            <?= $conteudo ?? '' ?>
        </div>
    </main>
    <script src="<?= e(url('/assets/js/app.js')) ?>" defer></script>
</body>
</html>
