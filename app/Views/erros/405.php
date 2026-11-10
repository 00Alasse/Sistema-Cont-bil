<?php declare(strict_types=1); ?>
<section>
    <h1><?= e($titulo ?? 'Método não permitido') ?></h1>
    <p><?= e($mensagem ?? 'O método usado não é permitido para esta página.') ?></p>
    <?php if (isset($metodosPermitidos) && is_array($metodosPermitidos) && $metodosPermitidos !== []): ?>
        <p>Métodos permitidos: <?= e(implode(', ', $metodosPermitidos)) ?></p>
    <?php endif; ?>
</section>
