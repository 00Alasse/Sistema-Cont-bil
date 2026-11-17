<?php

declare(strict_types=1);

use App\Core\UsuarioLogado;

$tituloPagina = is_string($titulo ?? null) ? $titulo : 'Portal do cliente';
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
    <style>
        .menu-icone { font-size: 0 !important; color: transparent !important; }
        html.sidebar-colapsada .estrutura-aplicacao,
        body.sidebar-colapsada .estrutura-aplicacao { grid-template-columns: 4.8rem minmax(0, 1fr); }
        html.sidebar-colapsada .navegacao-principal a,
        body.sidebar-colapsada .navegacao-principal a { font-size: 0 !important; justify-content: center; }
    </style>
    <script>
        (function() {
            try {
                if (localStorage.getItem('tributo_sidebar_colapsada') === '1' && window.innerWidth > 768) {
                    document.documentElement.classList.add('sidebar-colapsada');
                }
            } catch(e) {}
        })();
    </script>
    <link rel="stylesheet" href="<?= e(url('/assets/css/app.css')) ?>">
    <script src="<?= e(url('/assets/js/app.js')) ?>" defer></script>
</head>
<body class="portal">
    <a class="pular-conteudo" href="#conteudo-principal">Pular para o conteúdo</a>
    <header class="cabecalho">
        <div class="cabecalho-marca-area">
            <button id="botao-toggle-menu" class="botao-menu-toggle" type="button" aria-label="Minimizar ou maximizar menu" aria-expanded="true" title="Minimizar/Maximizar menu">
                <svg class="icone-horizontal" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <line x1="3" y1="6" x2="21" y2="6"></line>
                    <line x1="3" y1="12" x2="21" y2="12"></line>
                    <line x1="3" y1="18" x2="21" y2="18"></line>
                </svg>
                <svg class="icone-vertical" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <line x1="6" y1="3" x2="6" y2="21"></line>
                    <line x1="12" y1="3" x2="12" y2="21"></line>
                    <line x1="18" y1="3" x2="18" y2="21"></line>
                </svg>
            </button>
            <a class="marca" href="<?= e(url('/portal')) ?>"><span class="marca-hifen">-</span>Tributo!</a>
            <span class="badge-perfil">Portal Cliente</span>
        </div>
    </header>

    <div class="estrutura-aplicacao">
        <div id="menu-backdrop" class="menu-backdrop" aria-hidden="true"></div>
        <nav class="navegacao-principal" id="sidebar-principal" aria-label="Navegação principal">
            <div class="sidebar-topo-mobile">
                <span class="sidebar-titulo-mobile">Menu do Cliente</span>
                <button id="botao-fechar-menu-mobile" class="botao-fechar-mobile" type="button" aria-label="Fechar menu">✕</button>
            </div>
            <ul class="lista-menu-principal">
                <?php foreach ($itensMenu as $item) : ?>
                    <?php
                    $ehAtivo = ($item['url'] === '/portal' && ($caminhoAtual === '/portal' || $caminhoAtual === ''))
                        || ($item['url'] !== '/portal' && str_starts_with($caminhoAtual, $item['url']));
                    ?>
                    <li>
                        <a href="<?= e(url($item['url'])) ?>" data-icone="<?= e($item['icone']) ?>" class="<?= $ehAtivo ? 'item-ativo' : '' ?>" <?= $ehAtivo ? 'aria-current="page"' : '' ?> title="<?= e($item['rotulo']) ?>">
                            <span class="menu-icone" aria-hidden="true"><?= e(strtoupper(substr($item['icone'], 0, 1))) ?></span> <?= e($item['rotulo']) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>

            <?php if ($nomeUsuario !== '') : ?>
                <div class="sidebar-rodape-usuario">
                    <div class="usuario-info-sidebar">
                        <span class="avatar-sidebar" aria-hidden="true"><?= e(strtoupper(substr($nomeUsuario, 0, 1))) ?></span>
                        <div class="usuario-textos-sidebar">
                            <span class="usuario-nome-sidebar" title="<?= e($nomeUsuario) ?>"><?= e($nomeUsuario) ?></span>
                            <span class="usuario-perfil-sidebar">Portal Cliente</span>
                        </div>
                    </div>
                    <form method="post" action="<?= e(url('/logout')) ?>" class="form-logout-sidebar">
                        <?= csrf_campo() ?>
                        <button class="botao-sair-sidebar" type="submit" title="Encerrar sessão">
                            <span class="menu-icone icone-logout-svg" aria-hidden="true">S</span>
                            <span class="rotulo-sair">Sair</span>
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </nav>

        <main id="conteudo-principal" class="conteudo-principal" tabindex="-1">
            <?php require dirname(__DIR__) . '/partials/flash.php'; ?>
            <?= $conteudoPagina ?>
        </main>
    </div>
</body>
</html>
