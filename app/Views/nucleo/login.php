<?php declare(strict_types=1); ?>
<section>
    <div class="login-cabecalho">
        <h1 class="login-marca">-Tributo!</h1>
        <p class="login-subtitulo">Plataforma de Gestão Contábil</p>
        <h2>Entrar</h2>
    </div>

    <?php foreach ($mensagens ?? [] as $mensagem) : ?>
        <p role="alert" class="aviso aviso-<?= e($mensagem['tipo']) ?>"><?= e($mensagem['mensagem']) ?></p>
    <?php endforeach; ?>

    <form method="post" action="<?= e(url('/login')) ?>" novalidate>
        <?= csrf_campo() ?>

        <div class="campo">
            <label for="email">E-mail</label>
            <input type="email" id="email" name="email" maxlength="150" autocomplete="username"
                   value="<?= e((string) ($antigos['email'] ?? '')) ?>" placeholder="contador@teste.com" required autofocus>
            <?= erro_campo($erros ?? [], 'email') ?>
        </div>

        <div class="campo">
            <label for="senha">Senha</label>
            <input type="password" id="senha" name="senha" autocomplete="current-password" placeholder="Digite sua senha" required>
            <?= erro_campo($erros ?? [], 'senha') ?>
        </div>

        <button type="submit" class="botao botao-bloco">Entrar</button>
    </form>

    <div class="cartao" style="margin-top: 1.5rem; background: var(--cor-superficie); border: 1px solid var(--cor-borda); font-size: 0.875rem;">
        <p style="margin: 0 0 0.5rem; font-weight: 600; color: var(--cor-primaria);">🔑 Usuários de Demonstração:</p>
        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 0.75rem;">
            <button type="button" class="botao botao-secundario botao-pequeno" data-preencher-login data-email="contador@teste.com" data-senha="Senha@123">Preencher Contador</button>
            <button type="button" class="botao botao-secundario botao-pequeno" data-preencher-login data-email="empresario@teste.com" data-senha="Senha@123">Preencher Empresário</button>
        </div>
        <p style="margin: 0.25rem 0; color: var(--cor-texto-mutado);"><strong>Contador:</strong> <code>contador@teste.com</code></p>
        <p style="margin: 0.25rem 0; color: var(--cor-texto-mutado);"><strong>Empresário:</strong> <code>empresario@teste.com</code></p>
        <p style="margin: 0.25rem 0; color: var(--cor-texto-mutado);"><strong>Senha:</strong> <code>Senha@123</code> (ou <code>123456</code>)</p>
    </div>
</section>
