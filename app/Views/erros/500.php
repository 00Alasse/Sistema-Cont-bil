<?php declare(strict_types=1); ?>
<section>
    <h1><?= e($titulo ?? 'Erro interno') ?></h1>
    <p><?= e($mensagem ?? 'Ocorreu um erro inesperado. Tente novamente mais tarde.') ?></p>
</section>
