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
                   value="<?= e((string) ($antigos['email'] ?? '')) ?>" placeholder="exemplo@dominio.test" required autofocus>
            <?= erro_campo($erros ?? [], 'email') ?>
        </div>

        <div class="campo">
            <label for="senha">Senha</label>
            <input type="password" id="senha" name="senha" autocomplete="current-password" placeholder="Digite sua senha" required>
            <?= erro_campo($erros ?? [], 'senha') ?>
        </div>

        <button type="submit" class="botao botao-bloco">Entrar</button>
    </form>
</section>
