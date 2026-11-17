<?php

declare(strict_types=1);

use App\Core\UsuarioLogado;

/** @var UsuarioLogado|null $usuario */
$ehCliente = ($usuario instanceof UsuarioLogado) && $usuario->ehCliente();
$urlVoltar = $ehCliente ? '/portal' : '/painel';
$antigos = is_array($antigos ?? null) ? $antigos : [];
$erros = is_array($erros ?? null) ? $erros : [];
$notaAtual = (string) ($antigos['nota'] ?? '5');
$facilidadeAtual = (string) ($antigos['facilidade'] ?? '5');
?>
<section class="pagina">
    <header class="pagina-cabecalho">
        <div>
            <h1>Avaliação da Plataforma -Tributo</h1>
            <p>Sua opinião como usuário real é fundamental para o aprimoramento contínuo deste sistema.</p>
        </div>
        <div>
            <a href="<?= e(url($urlVoltar)) ?>" class="botao botao-secundario">← Voltar</a>
        </div>
    </header>

    <div class="cartao est-b1285b">
        <form method="post" action="<?= e(url('/avaliacao')) ?>">
            <?= csrf_campo() ?>

            <div class="campo campo-largo est-f0b24e">
                <label class="est-fc4e04">
                    1. Como você avalia a plataforma -Tributo de modo geral? <span class="est-6e512b">*</span>
                </label>
                <div class="est-db225d">
                    <?php for ($i = 5; $i >= 1; $i--) : ?>
                        <label class="est-d5a974">
                            <input type="radio" name="nota" value="<?= e((string) $i) ?>" <?= ($notaAtual === (string) $i) ? 'checked' : '' ?> required>
                            <?= e((string) $i) ?> <?= str_repeat('⭐', $i) ?>
                        </label>
                    <?php endfor; ?>
                </div>
                <?php if (!empty($erros['nota'])) : ?>
                    <span class="campo-erro"><?= e($erros['nota'][0]) ?></span>
                <?php endif; ?>
            </div>

            <div class="campo campo-largo est-f0b24e">
                <label class="est-fc4e04">
                    2. Facilidade de uso e navegação (1 = Muito difícil, 5 = Muito fácil):
                </label>
                <div class="est-db225d">
                    <?php for ($f = 5; $f >= 1; $f--) : ?>
                        <label class="est-d5a974">
                            <input type="radio" name="facilidade" value="<?= e((string) $f) ?>" <?= ($facilidadeAtual === (string) $f) ? 'checked' : '' ?>>
                            <?= e((string) $f) ?> - <?= match ($f) { 5 => 'Muito fácil', 4 => 'Fácil', 3 => 'Regular', 2 => 'Difícil', default => 'Muito difícil' } ?>
                        </label>
                    <?php endfor; ?>
                </div>
                <?php if (!empty($erros['facilidade'])) : ?>
                    <span class="campo-erro"><?= e($erros['facilidade'][0]) ?></span>
                <?php endif; ?>
            </div>

            <div class="campo campo-largo est-f0b24e">
                <label class="est-fc4e04" for="comentario">
                    3. Comentários, críticas ou sugestões de melhoria:
                </label>
                <textarea id="comentario" name="comentario" rows="5" placeholder="O que você mais gostou? O que pode ser melhorado na visualização das guias ou no envio de documentos?"><?= e((string) ($antigos['comentario'] ?? '')) ?></textarea>
                <?php if (!empty($erros['comentario'])) : ?>
                    <span class="campo-erro"><?= e($erros['comentario'][0]) ?></span>
                <?php endif; ?>
            </div>

            <div class="acoes-formulario est-5340b6">
                <button type="submit" class="botao botao-primario">Enviar Avaliação</button>
                <a href="<?= e(url($urlVoltar)) ?>" class="botao botao-secundario">Cancelar</a>
            </div>
        </form>
    </div>

    <?php require dirname(__DIR__) . '/partials/aviso-gerencial.php'; ?>
</section>
