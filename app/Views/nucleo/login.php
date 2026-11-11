<?php declare(strict_types=1); ?>
<section>
    <h1>-Tributo!</h1>
    <h2>Entrar</h2>

    <?php foreach ($mensagens ?? [] as $mensagem) : ?>
        <p role="alert" class="aviso aviso-<?= e($mensagem['tipo']) ?>"><?= e($mensagem['mensagem']) ?></p>
    <?php endforeach; ?>

    <form method="post" action="<?= e(url('/login')) ?>" novalidate>
        <?= csrf_campo() ?>

        <div>
            <label for="email">E-mail</label>
            <input type="email" id="email" name="email" maxlength="150" autocomplete="username"
                   value="<?= e((string) ($antigos['email'] ?? '')) ?>" required>
            <?= erro_campo($erros ?? [], 'email') ?>
        </div>

        <div>
            <label for="senha">Senha</label>
            <input type="password" id="senha" name="senha" autocomplete="current-password" required>
            <?= erro_campo($erros ?? [], 'senha') ?>
        </div>

        <button type="submit">Entrar</button>
    </form>
</section>
