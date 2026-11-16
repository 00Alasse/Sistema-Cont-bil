<?php declare(strict_types=1); ?>
<section class="cartao-acesso">
    <?php $marcaVariante = 'clara'; require dirname(__DIR__) . '/partials/marca.php'; ?>
    <h1>Entrar</h1>
    <p class="acesso-subtitulo">Acesse com o e-mail e a senha cadastrados pelo escritório.</p>

    <?php foreach ($mensagens ?? [] as $mensagem) : ?>
        <p role="alert" class="aviso aviso-com-icone aviso-<?= e($mensagem['tipo']) ?>">
            <?php
            $icone = ['sucesso' => 'sucesso', 'erro' => 'erro', 'aviso' => 'aviso'][$mensagem['tipo']] ?? 'info';
            require dirname(__DIR__) . '/partials/icone.php';
            ?>
            <span><?= e($mensagem['mensagem']) ?></span>
        </p>
    <?php endforeach; ?>

    <form class="formulario" method="post" action="<?= e(url('/login')) ?>" novalidate>
        <?= csrf_campo() ?>

        <div class="campo">
            <label for="email">E-mail</label>
            <input type="email" id="email" name="email" maxlength="150" autocomplete="username"
                   value="<?= e((string) ($antigos['email'] ?? '')) ?>" required>
            <?= erro_campo($erros ?? [], 'email') ?>
        </div>

        <div class="campo">
            <label for="senha">Senha</label>
            <input type="password" id="senha" name="senha" autocomplete="current-password" required>
            <?= erro_campo($erros ?? [], 'senha') ?>
        </div>

        <button class="botao" type="submit">Entrar</button>
    </form>
</section>