<?php

declare(strict_types=1);

$nivelSemaforo = is_string($nivel ?? null) ? $nivel : '';
$rotulosSemaforo = [
    'verde' => 'Regularidade em dia',
    'amarelo' => 'Atenção',
    'vermelho' => 'Pendência',
];
$rotuloSemaforo = $rotulosSemaforo[$nivelSemaforo] ?? null;
?>
<?php if ($rotuloSemaforo !== null) : ?>
    <span class="semaforo semaforo-<?= e($nivelSemaforo) ?>">
        <span class="semaforo-ponto" aria-hidden="true"></span>
        <?= e($rotuloSemaforo) ?>
    </span>
<?php endif; ?>
