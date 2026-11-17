<?php

declare(strict_types=1);

/*
 * Estrutura comum das áreas autenticadas (escritório e portal): barra lateral + cabeçalho + conteúdo.
 *
 * A barra lateral é um "offcanvas" do Bootstrap abaixo de 992 px (abre por botão, fecha por X, Esc ou
 * clique fora) e uma coluna fixa a partir daí, que o app.js deixa recolher. Sem JavaScript, o menu
 * continua visível como bloco no topo da página.
 *
 * Variáveis do chamador: $tituloPagina, $usuarioAtual, $conteudoPagina, $itensMenu, $marcaUrl,
 * $contextoCabecalho, $classeCorpo.
 */
$tituloPagina = is_string($tituloPagina ?? null) ? $tituloPagina : 'Painel';
$classeCorpo = is_string($classeCorpo ?? null) ? $classeCorpo : 'aplicacao';
$contextoCabecalho = is_string($contextoCabecalho ?? null) ? $contextoCabecalho : '';
$conteudoPagina = is_string($conteudoPagina ?? null) ? $conteudoPagina : '';
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0f2d57">
    <title><?= e($tituloPagina) ?> - -Tributo</title>
    <link rel="icon" type="image/svg+xml" href="<?= e(url('/assets/img/favicon.svg')) ?>">
    <link rel="stylesheet" href="<?= e(url('/assets/vendor/bootstrap/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(url('/assets/css/app.css')) ?>">
    <script src="<?= e(url('/assets/vendor/bootstrap/bootstrap.bundle.min.js')) ?>" defer></script>
    <script src="<?= e(url('/assets/js/app.js')) ?>" defer></script>
</head>
<body class="<?= e($classeCorpo) ?>">
    <a class="pular-conteudo" href="#conteudo-principal">Pular para o conteúdo</a>
    <div class="estrutura-aplicacao">
        <aside class="barra-lateral offcanvas-lg offcanvas-start" id="barra-lateral" tabindex="-1" aria-label="Menu lateral">
            <div class="barra-interna">
                <div class="barra-topo">
                    <?php require __DIR__ . '/marca.php'; ?>
                    <button class="btn-close btn-close-white d-lg-none" type="button" data-bs-dismiss="offcanvas" data-bs-target="#barra-lateral" aria-label="Fechar menu"></button>
                </div>
                <div class="barra-menu">
                    <?php require __DIR__ . '/navegacao.php'; ?>
                </div>
                <div class="barra-rodape">
                    <button class="barra-recolher" type="button" data-barra-recolher aria-expanded="true" title="Recolher menu">
                        <span class="recolher-icone recolher-icone-fechar" aria-hidden="true"><?php $icone = 'anterior'; require __DIR__ . '/icone.php'; ?></span>
                        <span class="recolher-icone recolher-icone-abrir" aria-hidden="true"><?php $icone = 'proximo'; require __DIR__ . '/icone.php'; ?></span>
                        <span class="barra-recolher-rotulo">Recolher menu</span>
                    </button>
                </div>
            </div>
        </aside>

        <div class="area-principal">
            <header class="cabecalho">
                <button class="btn btn-outline-secondary btn-sm d-lg-none botao-menu" type="button" data-bs-toggle="offcanvas" data-bs-target="#barra-lateral" aria-controls="barra-lateral">
                    <?php $icone = 'menu'; require __DIR__ . '/icone.php'; ?>
                    <span>Menu</span>
                </button>
                <p class="cabecalho-contexto"><?= e($contextoCabecalho) ?></p>
                <?php require __DIR__ . '/usuario-atual.php'; ?>
            </header>

            <main id="conteudo-principal" class="conteudo-principal" tabindex="-1">
                <?php require __DIR__ . '/flash.php'; ?>
                <?= $conteudoPagina ?>
            </main>
        </div>
    </div>
</body>
</html>
