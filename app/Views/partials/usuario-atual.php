<?php

declare(strict_types=1);

use App\Core\UsuarioLogado;

/*
 * Identificação de quem está logado e o botão Sair (POST com CSRF).
 *
 * Variável do chamador: $usuarioAtual (UsuarioLogado). Sem usuário, nada é impresso.
 */
$logado = $usuarioAtual ?? null;
$rotulosPerfil = ['ADMIN' => 'Administração', 'COLABORADOR' => 'Equipe', 'CLIENTE' => 'Cliente'];
$nomeLogado = $logado instanceof UsuarioLogado ? $logado->nome : '';
$perfilLogado = $logado instanceof UsuarioLogado ? ($rotulosPerfil[$logado->perfil] ?? '') : '';

$partesNome = preg_split('/\s+/u', trim($nomeLogado), -1, PREG_SPLIT_NO_EMPTY);
$partesNome = is_array($partesNome) ? $partesNome : [];
$iniciais = $partesNome === []
    ? '?'
    : mb_strtoupper(mb_substr($partesNome[0], 0, 1))
        . (count($partesNome) > 1 ? mb_strtoupper(mb_substr($partesNome[count($partesNome) - 1], 0, 1)) : '');
?>
<?php if ($nomeLogado !== '') : ?>
    <div class="usuario-atual">
        <span class="usuario-avatar" aria-hidden="true"><?= e($iniciais) ?></span>
        <span class="usuario-dados">
            <span class="usuario-nome"><?= e($nomeLogado) ?></span>
            <?php if ($perfilLogado !== '') : ?>
                <span class="usuario-perfil"><?= e($perfilLogado) ?></span>
            <?php endif; ?>
        </span>
        <form method="post" action="<?= e(url('/logout')) ?>">
            <?= csrf_campo() ?>
            <button class="botao botao-secundario botao-compacto" type="submit">
                <?php $icone = 'sair'; require __DIR__ . '/icone.php'; ?>
                <span>Sair</span>
            </button>
        </form>
    </div>
<?php endif; ?>
