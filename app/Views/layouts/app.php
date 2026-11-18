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
    <style>
        /* CSS Crítico anti-FOUC (renderização instantânea sem flash em transições de página) */
        *, *::before, *::after { box-sizing: border-box; }
        body { margin: 0; font-family: system-ui, -apple-system, "Segoe UI", sans-serif; background: #f8fafc; color: #172b4d; }
        .cabecalho { background: #153e75; min-height: 3.5rem; display: flex; align-items: center; padding: 0.5rem 1.25rem; color: #fff; }
        .cabecalho-marca-area { display: flex; align-items: center; gap: 0.75rem; }
        .marca { color: #fff; text-decoration: none; font-weight: 700; font-size: 1.25rem; }
        .botao-menu-toggle { display: inline-flex; align-items: center; justify-content: center; width: 2.3rem; height: 2.3rem; background: rgba(255,255,255,0.12); border: 1px solid rgba(255,255,255,0.22); border-radius: 0.375rem; color: #fff; cursor: pointer; }
        .botao-menu-toggle .icone-vertical { display: none; }
        .pular-conteudo { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0,0,0,0); }
        .estrutura-aplicacao { display: grid; grid-template-columns: 16rem minmax(0, 1fr); min-height: calc(100vh - 3.5rem); }
        .navegacao-principal { background: #f8fafc; border-right: 1px solid #e2e8f0; }
        .navegacao-principal ul { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 0.35rem; }
        .navegacao-principal a { text-decoration: none; color: inherit; display: flex; align-items: center; }
        .menu-icone { font-size: 0 !important; color: transparent !important; }
        .sidebar-topo-mobile { display: none; }
        @media (max-width: 768px) {
            .cabecalho { min-height: 3.25rem; padding: 0.5rem 0.75rem; }
            .estrutura-aplicacao { grid-template-columns: 3.8rem minmax(0, 1fr); }
            .navegacao-principal { width: 3.8rem; padding: 0.65rem 0; display: flex; flex-direction: column; align-items: center; }
            .navegacao-principal a { font-size: 0 !important; width: 2.5rem; height: 2.5rem; justify-content: center; }
            .sidebar-rodape-usuario .usuario-textos-sidebar { display: none; }
            .botao-sair-sidebar .rotulo-sair { display: none; }
            .botao-menu-toggle .icone-horizontal { display: none; }
            .botao-menu-toggle .icone-vertical { display: block; }
        }
    </style>
    <link rel="stylesheet" href="<?= e(url('/assets/css/app.css?v=2.2')) ?>">
    <script src="<?= e(url('/assets/js/app.js?v=2.2')) ?>" defer></script>
</head>
<body>
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
            <a class="marca" href="<?= e(url('/painel')) ?>"><span class="marca-hifen">-</span>Tributo!</a>
            <?php if ($perfilUsuario !== '') : ?>
                <span class="badge-perfil">
                    <?= $perfilUsuario === 'CLIENTE' ? 'Portal Cliente' : 'Escritório' ?>
                </span>
            <?php endif; ?>
        </div>
    </header>

    <div class="estrutura-aplicacao">
        <div id="menu-backdrop" class="menu-backdrop" aria-hidden="true"></div>
        <nav class="navegacao-principal" id="sidebar-principal" aria-label="Navegação principal">
            <div class="sidebar-topo-mobile">
                <span class="sidebar-titulo-mobile">Navegação</span>
                <button id="botao-fechar-menu-mobile" class="botao-fechar-mobile" type="button" aria-label="Fechar menu">✕</button>
            </div>
            <ul class="lista-menu-principal">
                <?php foreach ($itensMenu as $item) : ?>
                    <?php
                    $ehAtivo = ($item['url'] === '/painel' && ($caminhoAtual === '/painel' || $caminhoAtual === ''))
                        || ($item['url'] !== '/painel' && str_starts_with($caminhoAtual, $item['url']));
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
                            <span class="usuario-perfil-sidebar"><?= $perfilUsuario === 'CLIENTE' ? 'Portal Cliente' : 'Escritório' ?></span>
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
