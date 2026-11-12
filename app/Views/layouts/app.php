<?php

declare(strict_types=1);

use App\Core\UsuarioLogado;

$tituloPagina = is_string($titulo ?? null) ? $titulo : 'Painel';
$usuarioAtual = $usuario ?? null;
$nomeUsuario = $usuarioAtual instanceof UsuarioLogado ? $usuarioAtual->nome : '';
$conteudoPagina = is_string($conteudo ?? null) ? $conteudo : '';
/** @var list<array{rotulo: string, url: string, icone: string, ordem: int}> $itensMenu */
$itensMenu = is_array($itensMenu ?? null) ? $itensMenu : [];
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#153e75">
    <title><?= e($tituloPagina) ?> - -Tributo!</title>
    <link rel="stylesheet" href="<?= e(url('/assets/css/app.css')) ?>">
    <script src="<?= e(url('/assets/js/app.js')) ?>" defer></script>
</head>
<body>
    <a class="pular-conteudo" href="#conteudo-principal">Pular para o conteúdo</a>
    <header class="cabecalho">
        <a class="marca" href="<?= e(url('/painel')) ?>">-Tributo!</a>
        <?php if ($nomeUsuario !== '') : ?>
            <div class="usuario-atual">
                <span><?= e($nomeUsuario) ?></span>
                <form method="post" action="<?= e(url('/logout')) ?>">
                    <?= csrf_campo() ?>
                    <button class="botao botao-secundario" type="submit">Sair</button>
                </form>
            </div>
        <?php endif; ?>
    </header>

    <div class="estrutura-aplicacao">
        <nav class="navegacao-principal" aria-label="Navegação principal">
            <ul>
                <?php foreach ($itensMenu as $item) : ?>
                    <li>
                        <a href="<?= e(url($item['url'])) ?>">
                            <span class="menu-icone" aria-hidden="true"><?= e(strtoupper(substr($item['icone'], 0, 1))) ?></span>
                            <?= e($item['rotulo']) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>

        <main id="conteudo-principal" class="conteudo-principal" tabindex="-1">
            <?php require dirname(__DIR__) . '/partials/flash.php'; ?>
            <?= $conteudoPagina ?>
        </main>
    </div>
</body>
</html>
