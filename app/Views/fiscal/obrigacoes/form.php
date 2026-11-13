<?php

declare(strict_types=1);

$dados = is_array($dados ?? null) ? $dados : [];
$erros = is_array($erros ?? null) ? $erros : [];
$obrigacao = is_array($obrigacao ?? null) ? $obrigacao : null;
$empresas = is_array($empresas ?? null) ? $empresas : [];
$tipos = is_array($tipos ?? null) ? $tipos : [];
$responsaveis = is_array($responsaveis ?? null) ? $responsaveis : [];
$emEdicao = $obrigacao !== null;
$obrigacaoId = $emEdicao ? (int) ($obrigacao['id'] ?? 0) : 0;
$valor = static function (string $campo) use ($dados): string {
    $valor = $dados[$campo] ?? '';

    return is_scalar($valor) ? (string) $valor : '';
};
$registro = static fn (string $campo): string => is_scalar($obrigacao[$campo] ?? null) ? (string) $obrigacao[$campo] : '';
$selecionado = static fn (string $campo, int $id): string => $valor($campo) === (string) $id ? ' selected' : '';
$acao = $emEdicao ? '/obrigacoes/' . $obrigacaoId : '/obrigacoes';
$voltar = $emEdicao ? '/obrigacoes/' . $obrigacaoId : '/obrigacoes';
$rotulos = [
    'empresa_id' => 'Empresa',
    'tipo_obrigacao_id' => 'Tipo de obrigação',
    'competencia' => 'Competência',
    'vencimento' => 'Vencimento',
    'responsavel_id' => 'Responsável',
    'observacao' => 'Observação',
    'versao' => 'Versão',
];
$invalido = static fn (string $campo): string => isset($erros[$campo]) ? ' aria-invalid="true"' : '';

// Na edição, o responsável atual continua na lista mesmo se tiver sido desativado.
$idsResponsaveis = array_map(static fn (array $r): int => (int) $r['id'], $responsaveis);
$responsavelAtual = $emEdicao ? $registro('responsavel_id') : '';
if ($responsavelAtual !== '' && !in_array((int) $responsavelAtual, $idsResponsaveis, true)) {
    $responsaveis[] = ['id' => (int) $responsavelAtual, 'nome' => $registro('responsavel_nome') . ' (inativo)'];
}
?>
<section class="pagina pagina-formulario">
    <header class="pagina-cabecalho">
        <div>
            <h1><?= e($emEdicao ? 'Editar obrigação' : 'Cadastrar obrigação') ?></h1>
            <p>O vencimento é informado pelo escritório e não é conferido pelo sistema: confira o prazo vigente na fonte oficial.</p>
        </div>
        <a class="botao botao-secundario" href="<?= e(url($voltar)) ?>">Cancelar</a>
    </header>

    <form class="cartao formulario" method="post" action="<?= e(url($acao)) ?>">
        <?= csrf_campo() ?>
        <?php if ($erros !== []) : ?>
            <div class="aviso aviso-erro" role="alert">
                <p>Revise os campos indicados:</p>
                <ul>
                    <?php foreach ($erros as $campoErro => $mensagensErro) : ?>
                        <?php foreach (is_array($mensagensErro) ? $mensagensErro : [] as $mensagemErro) : ?>
                            <?php if (is_string($mensagemErro)) : ?>
                                <li><strong><?= e($rotulos[$campoErro] ?? (string) $campoErro) ?>:</strong> <?= e($mensagemErro) ?></li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($emEdicao) : ?>
            <?php $competencia = $registro('competencia'); ?>
            <dl class="detalhes-empresa">
                <div><dt>Empresa</dt><dd><?= e($registro('empresa_nome')) ?></dd></div>
                <div><dt>Tipo</dt><dd><?= e($registro('tipo_nome')) ?></dd></div>
                <div><dt>Competência</dt><dd><?= e(substr($competencia, 5, 2) . '/' . substr($competencia, 0, 4)) ?></dd></div>
            </dl>
            <p>Empresa, tipo e competência não mudam depois do cadastro. O status é alterado em outra tela.</p>
            <input type="hidden" name="versao" value="<?= e($valor('versao')) ?>">
        <?php endif; ?>

        <div class="grade-formulario">
            <?php if (!$emEdicao) : ?>
                <div class="campo campo-largo">
                    <label for="empresa_id">Empresa <span aria-hidden="true">*</span></label>
                    <select id="empresa_id" name="empresa_id" required<?= $invalido('empresa_id') ?>>
                        <option value="">Selecione</option>
                        <?php foreach ($empresas as $empresa) : ?>
                            <option value="<?= e((string) $empresa['id']) ?>"<?= $selecionado('empresa_id', (int) $empresa['id']) ?>><?= e($empresa['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php $campo = 'empresa_id'; require dirname(__DIR__, 2) . '/partials/campo-erro.php'; ?>
                </div>
                <div class="campo campo-largo">
                    <label for="tipo_obrigacao_id">Tipo de obrigação <span aria-hidden="true">*</span></label>
                    <select id="tipo_obrigacao_id" name="tipo_obrigacao_id" required<?= $invalido('tipo_obrigacao_id') ?>>
                        <option value="">Selecione</option>
                        <?php foreach ($tipos as $tipo) : ?>
                            <option value="<?= e((string) $tipo['id']) ?>"<?= $selecionado('tipo_obrigacao_id', (int) $tipo['id']) ?>><?= e($tipo['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php $campo = 'tipo_obrigacao_id'; require dirname(__DIR__, 2) . '/partials/campo-erro.php'; ?>
                </div>
                <div class="campo">
                    <label for="competencia">Competência <span aria-hidden="true">*</span></label>
                    <input id="competencia" name="competencia" type="month" placeholder="AAAA-MM" required
                        value="<?= e($valor('competencia')) ?>"<?= $invalido('competencia') ?>>
                    <?php $campo = 'competencia'; require dirname(__DIR__, 2) . '/partials/campo-erro.php'; ?>
                </div>
            <?php endif; ?>
            <div class="campo">
                <label for="vencimento">Vencimento <span aria-hidden="true">*</span></label>
                <?php // Na criação, o campo pode ir em branco para receber a data sugerida pelo tipo. ?>
                <input id="vencimento" name="vencimento" type="date"<?= $emEdicao ? ' required' : '' ?>
                    value="<?= e($valor('vencimento')) ?>"<?= $invalido('vencimento') ?>>
                <?php if (!$emEdicao) : ?>
                    <small>Se deixar em branco, o sistema sugere a data calculada pelo tipo para você conferir.</small>
                <?php endif; ?>
                <?php $campo = 'vencimento'; require dirname(__DIR__, 2) . '/partials/campo-erro.php'; ?>
            </div>
            <div class="campo">
                <label for="responsavel_id">Responsável</label>
                <select id="responsavel_id" name="responsavel_id"<?= $invalido('responsavel_id') ?>>
                    <option value="">Sem responsável</option>
                    <?php foreach ($responsaveis as $responsavel) : ?>
                        <option value="<?= e((string) $responsavel['id']) ?>"<?= $selecionado('responsavel_id', (int) $responsavel['id']) ?>><?= e($responsavel['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php $campo = 'responsavel_id'; require dirname(__DIR__, 2) . '/partials/campo-erro.php'; ?>
            </div>
            <div class="campo campo-largo">
                <label for="observacao">Observação interna</label>
                <textarea id="observacao" name="observacao" rows="4"<?= $invalido('observacao') ?>><?= e($valor('observacao')) ?></textarea>
                <small>Uso interno do escritório; não é exibida ao cliente.</small>
                <?php $campo = 'observacao'; require dirname(__DIR__, 2) . '/partials/campo-erro.php'; ?>
            </div>
        </div>

        <div class="acoes-formulario">
            <button class="botao" type="submit"><?= e($emEdicao ? 'Salvar alterações' : 'Cadastrar obrigação') ?></button>
        </div>
    </form>
</section>
