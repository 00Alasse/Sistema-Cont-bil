<?php

declare(strict_types=1);

$tituloPagina = is_string($titulo ?? null) ? $titulo : 'Portal do cliente';
$usuarioAtual = $usuario ?? null;
$conteudoPagina = is_string($conteudo ?? null) ? $conteudo : '';
/** @var list<array{rotulo: string, url: string, icone: string, ordem: int}> $itensMenu */
$itensMenu = is_array($itensMenu ?? null) ? $itensMenu : [];
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0f2d57">
    <title><?= e($tituloPagina) ?> - -Tributo</title>
    <link rel="icon" type="image/svg+xml" href="<?= e(url('/assets/img/favicon.svg')) ?>">
    <link rel="stylesheet" href="<?= e(url('/assets/css/app.css')) ?>">
    <script src="<?= e(url('/assets/js/app.js')) ?>" defer></script>
</head>
<body class="aplicacao portal">
    <a class="pular-conteudo" href="#conteudo-principal">Pular para o conteúdo</a>
    <div class="estrutura-aplicacao">
        <div class="barra-lateral">
            <div class="barra-topo">
                <?php $marcaUrl = '/portal'; require __DIR__ . '/../partials/marca.php'; ?>
                <button class="menu-botao" type="button" data-menu-botao aria-controls="menu-principal" aria-expanded="false">
                    <?php $icone = 'menu'; require __DIR__ . '/../partials/icone.php'; ?>
                    <span class="texto-acessivel">Menu</span>
                </button>
            </div>
            <div class="barra-menu" id="menu-principal">
                <?php require __DIR__ . '/../partials/navegacao.php'; ?>
            </div>
        </div>

        <div class="area-principal">
            <header class="cabecalho">
                <p class="cabecalho-contexto">Portal do cliente</p>
                <?php require __DIR__ . '/../partials/usuario-atual.php'; ?>
            </header>

            <main id="conteudo-principal" class="conteudo-principal" tabindex="-1">
                <?php require __DIR__ . '/../partials/flash.php'; ?>
                <?= $conteudoPagina ?>
            </main>
        </div>
    </div>
</body>
</html>
