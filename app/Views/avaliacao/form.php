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
            <a href="<?= e(url($urlVoltar)) ?>" class="btn btn-outline-secondary">← Voltar</a>
        </div>
    </header>

    <div class="row">
        <div class="col-lg-8 col-xl-7">
            <form class="card card-body formulario" method="post" action="<?= e(url('/avaliacao')) ?>">
                <?= csrf_campo() ?>

                <fieldset class="mb-4">
                    <legend class="form-label fw-semibold fs-6">
                        1. Como você avalia a plataforma -Tributo de modo geral? <span aria-hidden="true">*</span>
                    </legend>
                    <div class="d-flex flex-wrap gap-3">
                        <?php for ($i = 5; $i >= 1; $i--) : ?>
                            <div class="form-check form-check-inline m-0">
                                <input class="form-check-input" type="radio" id="nota_<?= e((string) $i) ?>" name="nota" value="<?= e((string) $i) ?>" <?= ($notaAtual === (string) $i) ? 'checked' : '' ?> required>
                                <label class="form-check-label" for="nota_<?= e((string) $i) ?>">
                                    <?= e((string) $i) ?> <?= str_repeat('⭐', $i) ?>
                                </label>
                            </div>
                        <?php endfor; ?>
                    </div>
                    <?php $campo = 'nota'; require dirname(__DIR__) . '/partials/campo-erro.php'; ?>
                </fieldset>

                <fieldset class="mb-4">
                    <legend class="form-label fw-semibold fs-6">
                        2. Facilidade de uso e navegação (1 = Muito difícil, 5 = Muito fácil):
                    </legend>
                    <div class="d-flex flex-wrap gap-3">
                        <?php for ($f = 5; $f >= 1; $f--) : ?>
                            <div class="form-check form-check-inline m-0">
                                <input class="form-check-input" type="radio" id="facilidade_<?= e((string) $f) ?>" name="facilidade" value="<?= e((string) $f) ?>" <?= ($facilidadeAtual === (string) $f) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="facilidade_<?= e((string) $f) ?>">
                                    <?= e((string) $f) ?> - <?= match ($f) { 5 => 'Muito fácil', 4 => 'Fácil', 3 => 'Regular', 2 => 'Difícil', default => 'Muito difícil' } ?>
                                </label>
                            </div>
                        <?php endfor; ?>
                    </div>
                    <?php $campo = 'facilidade'; require dirname(__DIR__) . '/partials/campo-erro.php'; ?>
                </fieldset>

                <div class="mb-4">
                    <label class="form-label fw-semibold fs-6" for="comentario">
                        3. Comentários, críticas ou sugestões de melhoria:
                    </label>
                    <textarea class="form-control" id="comentario" name="comentario" rows="5" placeholder="O que você mais gostou? O que pode ser melhorado na visualização das guias ou no envio de documentos?"><?= e((string) ($antigos['comentario'] ?? '')) ?></textarea>
                    <?php $campo = 'comentario'; require dirname(__DIR__) . '/partials/campo-erro.php'; ?>
                </div>

                <div class="d-flex gap-3">
                    <button type="submit" class="btn btn-primary">Enviar Avaliação</button>
                    <a href="<?= e(url($urlVoltar)) ?>" class="btn btn-outline-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>

    <?php require dirname(__DIR__) . '/partials/aviso-gerencial.php'; ?>
</section>
