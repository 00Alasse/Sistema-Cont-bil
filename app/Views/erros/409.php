<?php declare(strict_types=1); ?>
<section>
    <h1><?= e($titulo ?? 'Conflito') ?></h1>
    <p><?= e($mensagem ?? 'Não foi possível concluir a operação porque os dados foram alterados.') ?></p>
</section>
