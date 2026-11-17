<?php

declare(strict_types=1);

use App\Core\UsuarioLogado;

$tituloPagina = is_string($titulo ?? null) ? $titulo : 'Painel';
$usuarioAtual = $usuario ?? null;
$nomeUsuario = $usuarioAtual instanceof UsuarioLogado ? $usuarioAtual->nome : '';
$perfilUsuario = $usuarioAtual instanceof UsuarioLogado ? $usuarioAtual->perfil : '';
$conteudoPagina = is_string($conteudo ?? null) ? $conteudo : '';
/** @var list<array{rotulo: string, url: string, icone: string, ordem: int}> $itensMenu */
$itensMenu = is_array($itensMenu ?? null) ? $itensMenu : [];
$caminhoAtual = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5">
    <meta name="theme-color" content="#153e75">
    <title><?= e($tituloPagina) ?> - -Tributo!</title>
    <link rel="stylesheet" href="<?= e(url('/assets/css/app.css')) ?>">
    <script src="<?= e(url('/assets/js/app.js')) ?>" defer></script>
</head>
<body>
    <a class="pular-conteudo" href="#conteudo-principal">Pular para o conteúdo</a>
    <header class="cabecalho">
        <div class="cabecalho-marca-area" style="display: flex; align-items: center; gap: 0.6rem;">
            <a class="marca" href="<?= e(url('/painel')) ?>">-Tributo!</a>
            <?php if ($perfilUsuario !== '') : ?>
                <span style="font-size: 0.7rem; background: rgba(255,255,255,0.18); padding: 0.2rem 0.5rem; border-radius: 9999px; color: #e2e8f0; font-weight: 500;">
                    <?= $perfilUsuario === 'CLIENTE' ? 'Portal Cliente' : 'Escritório' ?>
                </span>
            <?php endif; ?>
        </div>
        <?php if ($nomeUsuario !== '') : ?>
            <div class="usuario-atual">
                <span class="usuario-nome" title="<?= e($nomeUsuario) ?>"><?= e($nomeUsuario) ?></span>
                <form method="post" action="<?= e(url('/logout')) ?>" style="margin: 0;">
                    <?= csrf_campo() ?>
                    <button class="botao-sair" type="submit" title="Encerrar sessão">Sair</button>
                </form>
            </div>
        <?php endif; ?>
    </header>

    <div class="estrutura-aplicacao">
        <nav class="navegacao-principal" aria-label="Navegação principal">
            <ul>
                <?php foreach ($itensMenu as $item) : ?>
                    <?php
                    $ehAtivo = ($item['url'] === '/painel' && ($caminhoAtual === '/painel' || $caminhoAtual === ''))
                        || ($item['url'] !== '/painel' && str_starts_with($caminhoAtual, $item['url']));
                    ?>
                    <li>
                        <a href="<?= e(url($item['url'])) ?>" class="<?= $ehAtivo ? 'item-ativo' : '' ?>" <?= $ehAtivo ? 'aria-current="page"' : '' ?>>
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
