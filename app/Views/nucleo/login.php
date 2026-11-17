<?php declare(strict_types=1); ?>
<section class="cartao-acesso">
    <?php $marcaVariante = 'clara'; require dirname(__DIR__) . '/partials/marca.php'; ?>
    <h1>Entrar</h1>
    <p class="acesso-subtitulo">Acesse com o e-mail e a senha cadastrados pelo escritório.</p>

    <?php foreach ($mensagens ?? [] as $mensagem) : ?>
        <p role="alert" class="alert alert-com-icone alert-<?= e(["sucesso" => "success", "erro" => "danger", "aviso" => "warning"][$mensagem["tipo"]] ?? "info") ?>">
            <?php
            $icone = ['sucesso' => 'sucesso', 'erro' => 'erro', 'aviso' => 'aviso'][$mensagem['tipo']] ?? 'info';
            require dirname(__DIR__) . '/partials/icone.php';
            ?>
            <span><?= e($mensagem['mensagem']) ?></span>
        </p>
    <?php endforeach; ?>

    <form class="formulario" method="post" action="<?= e(url('/login')) ?>" novalidate>
        <?= csrf_campo() ?>

        <div class="mb-3">
            <label class="form-label" for="email">E-mail</label>
            <input class="form-control" type="email" id="email" name="email" maxlength="150" autocomplete="username"
                   value="<?= e((string) ($antigos['email'] ?? '')) ?>" placeholder="contador@teste.com" required autofocus>
            <?= erro_campo($erros ?? [], 'email') ?>
        </div>

        <div class="mb-3">
            <label class="form-label" for="senha">Senha</label>
            <input class="form-control" type="password" id="senha" name="senha" autocomplete="current-password" placeholder="Digite sua senha" required>
            <?= erro_campo($erros ?? [], 'senha') ?>
        </div>

        <button class="btn btn-primary w-100" type="submit">Entrar</button>
    </form>

    <div class="login-demo">
        <p class="login-demo-titulo">Usuários de demonstração</p>
        <div class="login-demo-botoes">
            <button type="button" class="btn btn-outline-secondary btn-sm" data-preencher-login data-email="contador@teste.com" data-senha="Senha@123">Preencher Contador</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" data-preencher-login data-email="empresario@teste.com" data-senha="Senha@123">Preencher Empresário</button>
        </div>
        <p><strong>Contador:</strong> <code>contador@teste.com</code></p>
        <p><strong>Empresário:</strong> <code>empresario@teste.com</code></p>
        <p><strong>Senha:</strong> <code>Senha@123</code> (ou <code>123456</code>)</p>
    </div>
</section>
