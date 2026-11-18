<?php

declare(strict_types=1);

$empresas = is_array($empresas ?? null) ? $empresas : [];
$antigos = is_array($antigos ?? null) ? $antigos : [];
$erros = is_array($erros ?? null) ? $erros : [];

$empresaSelecionada = (string) ($antigos['empresa_id'] ?? '');
?>
<section class="pagina pagina-formulario">
    <header class="pagina-cabecalho">
        <div>
            <h1>Nova Solicitação de Documento</h1>
            <p>Envie um pedido de documento contábil para o portal do seu cliente.</p>
        </div>
        <a class="btn btn-outline-secondary" href="<?= e(url('/solicitacoes')) ?>">← Voltar para a lista</a>
    </header>

    <form class="card card-body formulario" method="post" action="<?= e(url('/solicitacoes')) ?>">
        <?= csrf_campo() ?>

        <div class="row g-3">
            <div class="col-12">
                <label class="form-label" for="empresa_id">Empresa <span aria-hidden="true">*</span></label>
                <select class="form-select" id="empresa_id" name="empresa_id" required<?= isset($erros['empresa_id']) ? ' aria-invalid="true"' : '' ?>>
                    <option value="">Selecione uma empresa...</option>
                    <?php foreach ($empresas as $emp) : ?>
                        <option value="<?= e((string) $emp['id']) ?>" <?= ($empresaSelecionada === (string) $emp['id']) ? 'selected' : '' ?>>
                            <?= e($emp['razao_social']) ?> (CNPJ: <?= e($emp['cnpj']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php $campo = 'empresa_id'; require dirname(__DIR__, 2) . '/partials/campo-erro.php'; ?>
            </div>

            <div class="col-12">
                <label class="form-label" for="titulo">Título do Documento Solicitado <span aria-hidden="true">*</span></label>
                <input class="form-control" id="titulo" name="titulo" type="text" value="<?= e((string) ($antigos['titulo'] ?? '')) ?>" required<?= isset($erros['titulo']) ? ' aria-invalid="true"' : '' ?> placeholder="Ex.: Extrato Bancário de Outubro, Notas Fiscais de Entrada...">
                <?php $campo = 'titulo'; require dirname(__DIR__, 2) . '/partials/campo-erro.php'; ?>
            </div>

            <div class="col-md-4">
                <label class="form-label" for="prazo">Data Limite (Prazo para o cliente) <span aria-hidden="true">*</span></label>
                <input class="form-control" id="prazo" name="prazo" type="date" value="<?= e((string) ($antigos['prazo'] ?? '')) ?>" required<?= isset($erros['prazo']) ? ' aria-invalid="true"' : '' ?>>
                <?php $campo = 'prazo'; require dirname(__DIR__, 2) . '/partials/campo-erro.php'; ?>
            </div>

            <div class="col-12">
                <label class="form-label" for="descricao">Instruções / Orientações para o Cliente (Opcional)</label>
                <textarea class="form-control" id="descricao" name="descricao" rows="4"<?= isset($erros['descricao']) ? ' aria-invalid="true"' : '' ?> placeholder="Ex.: Favor anexar o extrato completo em formato PDF ou OFX referente ao mês passado."><?= e((string) ($antigos['descricao'] ?? '')) ?></textarea>
                <?php $campo = 'descricao'; require dirname(__DIR__, 2) . '/partials/campo-erro.php'; ?>
            </div>
        </div>

        <div class="d-flex gap-3 mt-4">
            <button type="submit" class="btn btn-primary">Criar Solicitação</button>
            <a href="<?= e(url('/solicitacoes')) ?>" class="btn btn-outline-secondary">Cancelar</a>
        </div>
    </form>

    <?php require dirname(__DIR__, 2) . '/partials/aviso-gerencial.php'; ?>
</section>
