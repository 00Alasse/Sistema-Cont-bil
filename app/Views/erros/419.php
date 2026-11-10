<?php declare(strict_types=1); ?>
<section>
    <h1><?= e($titulo ?? 'Solicitação expirada') ?></h1>
    <p><?= e($mensagem ?? 'Não foi possível confirmar esta solicitação. Atualize a página e tente novamente.') ?></p>
</section>
