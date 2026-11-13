<?php

declare(strict_types=1);

/*
 * Formulário de mudança de status (FI04), incluído por show.php.
 * Só lista os destinos que a matriz permite para o status atual e o perfil logado.
 */
$destinos = is_array($destinos ?? null) ? $destinos : [];
$dadosStatus = is_array($dadosStatus ?? null) ? $dadosStatus : [];
$errosStatus = is_array($errosStatus ?? null) ? $errosStatus : [];
$conflitoStatus = is_string($conflitoStatus ?? null) ? $conflitoStatus : null;
$rotulosStatus = is_array($rotulosStatus ?? null) ? $rotulosStatus : [];
$obrigacaoId = (int) ($obrigacaoId ?? 0);
$statusAtual = is_array($obrigacao ?? null) && is_scalar($obrigacao['status'] ?? null) ? (string) $obrigacao['status'] : '';
$valorStatus = static fn (string $campo): string => is_scalar($dadosStatus[$campo] ?? null) ? (string) $dadosStatus[$campo] : '';
$rotulosCampos = ['status' => 'Novo status', 'justificativa' => 'Justificativa', 'protocolo' => 'Protocolo', 'versao' => 'Versão'];
// O partial de erro por campo lê $erros e $campo.
$erros = $errosStatus;
?>
<section class="cartao" aria-labelledby="titulo-status">
    <h2 id="titulo-status">Mudar status</h2>

    <?php if ($conflitoStatus !== null) : ?>
        <div class="aviso aviso-erro" role="alert">
            <p><?= e($conflitoStatus) ?> Confira a situação atual antes de tentar de novo.</p>
        </div>
    <?php endif; ?>

    <?php if ($destinos === []) : ?>
        <p>Não há mudança de status disponível para o seu perfil a partir de "<?= e((string) ($rotulosStatus[$statusAtual] ?? $statusAtual)) ?>".</p>
    <?php else : ?>
        <form class="formulario" method="post" action="<?= e(url('/obrigacoes/' . $obrigacaoId . '/status')) ?>">
            <?= csrf_campo() ?>
            <input type="hidden" name="versao" value="<?= e($valorStatus('versao')) ?>">

            <?php if ($errosStatus !== []) : ?>
                <div class="aviso aviso-erro" role="alert">
                    <p>Revise os campos indicados:</p>
                    <ul>
                        <?php foreach ($errosStatus as $campoErro => $mensagensErro) : ?>
                            <?php foreach (is_array($mensagensErro) ? $mensagensErro : [] as $mensagemErro) : ?>
                                <?php if (is_string($mensagemErro)) : ?>
                                    <li><strong><?= e($rotulosCampos[$campoErro] ?? (string) $campoErro) ?>:</strong> <?= e($mensagemErro) ?></li>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="grade-formulario">
                <div class="campo">
                    <label for="status">Novo status <span aria-hidden="true">*</span></label>
                    <select id="status" name="status" required<?= isset($errosStatus['status']) ? ' aria-invalid="true"' : '' ?>>
                        <option value="">Selecione</option>
                        <?php foreach ($destinos as $destino) : ?>
                            <option value="<?= e((string) $destino) ?>"<?= $valorStatus('status') === $destino ? ' selected' : '' ?>><?= e((string) ($rotulosStatus[$destino] ?? $destino)) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php $campo = 'status'; require dirname(__DIR__, 2) . '/partials/campo-erro.php'; ?>
                </div>
                <div class="campo">
                    <label for="protocolo">Protocolo</label>
                    <input id="protocolo" name="protocolo" type="text" maxlength="100"
                        value="<?= e($valorStatus('protocolo')) ?>"<?= isset($errosStatus['protocolo']) ? ' aria-invalid="true"' : '' ?>>
                    <small>Opcional; só na mudança para Entregue.</small>
                    <?php $campo = 'protocolo'; require dirname(__DIR__, 2) . '/partials/campo-erro.php'; ?>
                </div>
                <div class="campo campo-largo">
                    <label for="justificativa">Justificativa</label>
                    <textarea id="justificativa" name="justificativa" rows="3" maxlength="500"<?= isset($errosStatus['justificativa']) ? ' aria-invalid="true"' : '' ?>><?= e($valorStatus('justificativa')) ?></textarea>
                    <small>Obrigatória para dispensar e para reabrir; até 500 caracteres.</small>
                    <?php $campo = 'justificativa'; require dirname(__DIR__, 2) . '/partials/campo-erro.php'; ?>
                </div>
            </div>

            <div class="acoes-formulario">
                <button class="botao" type="submit">Mudar status</button>
            </div>
        </form>
    <?php endif; ?>
</section>
