<?php

declare(strict_types=1);

$empresas = is_array($empresas ?? null) ? $empresas : [];
$antigos = is_array($antigos ?? null) ? $antigos : [];
$erros = is_array($erros ?? null) ? $erros : [];

$empresaSelecionada = (string) ($antigos['empresa_id'] ?? '');
?>
<section class="pagina">
    <header class="pagina-cabecalho">
        <div>
            <h1>Nova Solicitação de Documento</h1>
            <p>Envie um pedido de documento contábil para o portal do seu cliente.</p>
        </div>
        <a class="botao botao-secundario" href="<?= e(url('/solicitacoes')) ?>">← Voltar para a lista</a>
    </header>

    <div class="cartao">
        <form method="post" action="<?= e(url('/solicitacoes')) ?>">
            <?= csrf_campo() ?>

            <div class="grade-formulario">
                <div class="campo campo-largo">
                    <label for="empresa_id">Empresa <span class="est-6e512b">*</span></label>
                    <select id="empresa_id" name="empresa_id" required>
                        <option value="">Selecione uma empresa...</option>
                        <?php foreach ($empresas as $emp) : ?>
                            <option value="<?= e((string) $emp['id']) ?>" <?= ($empresaSelecionada === (string) $emp['id']) ? 'selected' : '' ?>>
                                <?= e($emp['razao_social']) ?> (CNPJ: <?= e($emp['cnpj']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!empty($erros['empresa_id'])) : ?>
                        <span class="campo-erro"><?= e($erros['empresa_id'][0]) ?></span>
                    <?php endif; ?>
                </div>

                <div class="campo campo-largo">
                    <label for="titulo">Título do Documento Solicitado <span class="est-6e512b">*</span></label>
                    <input id="titulo" name="titulo" type="text" value="<?= e((string) ($antigos['titulo'] ?? '')) ?>" required placeholder="Ex.: Extrato Bancário de Outubro, Notas Fiscais de Entrada...">
                    <?php if (!empty($erros['titulo'])) : ?>
                        <span class="campo-erro"><?= e($erros['titulo'][0]) ?></span>
                    <?php endif; ?>
                </div>

                <div class="campo">
                    <label for="prazo">Data Limite (Prazo para o cliente) <span class="est-6e512b">*</span></label>
                    <input id="prazo" name="prazo" type="date" value="<?= e((string) ($antigos['prazo'] ?? '')) ?>" required>
                    <?php if (!empty($erros['prazo'])) : ?>
                        <span class="campo-erro"><?= e($erros['prazo'][0]) ?></span>
                    <?php endif; ?>
                </div>

                <div class="campo campo-largo">
                    <label for="descricao">Instruções / Orientações para o Cliente (Opcional)</label>
                    <textarea id="descricao" name="descricao" rows="4" placeholder="Ex.: Favor anexar o extrato completo em formato PDF ou OFX referente ao mês passado."><?= e((string) ($antigos['descricao'] ?? '')) ?></textarea>
                    <?php if (!empty($erros['descricao'])) : ?>
                        <span class="campo-erro"><?= e($erros['descricao'][0]) ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="acoes-formulario est-258410">
                <button type="submit" class="botao botao-primario">Criar Solicitação</button>
                <a href="<?= e(url('/solicitacoes')) ?>" class="botao botao-secundario">Cancelar</a>
            </div>
        </form>
    </div>

    <?php require dirname(__DIR__, 2) . '/partials/aviso-gerencial.php'; ?>
</section>
