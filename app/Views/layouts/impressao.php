<?php

declare(strict_types=1);

$tituloPagina = is_string($titulo ?? null) ? $titulo : 'Documento';
$conteudoPagina = is_string($conteudo ?? null) ? $conteudo : '';
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($tituloPagina) ?> - -Tributo!</title>
    <link rel="stylesheet" href="<?= e(url('/assets/css/impressao.css')) ?>" media="print">
</head>
<body>
    <main class="documento-impressao">
        <h1><?= e($tituloPagina) ?></h1>
        <?= $conteudoPagina ?>
    </main>
</body>
</html>
