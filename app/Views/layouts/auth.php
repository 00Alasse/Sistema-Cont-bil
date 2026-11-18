<?php

declare(strict_types=1);

$cssCaminho = dirname(__DIR__, 3) . '/public/assets/css/app.css';
$cssCompleto = is_file($cssCaminho) ? (string) file_get_contents($cssCaminho) : '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#153e75">
    <title><?= e($titulo ?? 'Entrar') ?> - -Tributo!</title>
    <?php if ($cssCompleto !== '') : ?>
        <style><?= $cssCompleto ?></style>
    <?php else : ?>
        <link rel="stylesheet" href="<?= e(url('/assets/css/app.css?v=2.3')) ?>">
    <?php endif; ?>
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
