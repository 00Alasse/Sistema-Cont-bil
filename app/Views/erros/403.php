<?php declare(strict_types=1); ?>
<section>
    <h1><?= e($titulo ?? 'Acesso negado') ?></h1>
    <p><?= e($mensagem ?? 'Você não tem permissão para acessar esta página.') ?></p>
</section>
