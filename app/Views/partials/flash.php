<?php

declare(strict_types=1);

use App\Core\Session;

/** @var list<array{tipo: string, mensagem: string}> $mensagensFlash */
$mensagensFlash = is_array($mensagens ?? null)
    ? $mensagens
    : Session::atual()->consumirFlash();
?>
<?php if ($mensagensFlash !== []) : ?>
    <div class="mensagens-flash" aria-live="polite">
        <?php foreach ($mensagensFlash as $mensagem) : ?>
            <?php
            $tipo = in_array($mensagem['tipo'], ['sucesso', 'erro', 'aviso', 'info'], true)
                ? $mensagem['tipo']
                : 'info';
            $papel = $tipo === 'erro' ? 'alert' : 'status';
            ?>
            <p class="aviso aviso-com-icone aviso-<?= e($tipo) ?>" role="<?= e($papel) ?>">
                <?php
                $icone = ['sucesso' => 'sucesso', 'erro' => 'erro', 'aviso' => 'aviso'][$tipo] ?? 'info';
                require __DIR__ . '/icone.php';
                ?>
                <span><?= e($mensagem['mensagem']) ?></span>
            </p>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
