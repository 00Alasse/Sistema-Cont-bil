<?php

declare(strict_types=1);

$dados = is_array($dados ?? null) ? $dados : [];
$erros = is_array($erros ?? null) ? $erros : [];
$tipoId = is_int($tipoId ?? null) ? $tipoId : null;
$emEdicao = $tipoId !== null;
$valor = static function (string $campo) use ($dados): string {
    $valor = $dados[$campo] ?? '';

    return is_scalar($valor) ? (string) $valor : '';
};
$acao = $emEdicao ? '/tipos-obrigacao/' . $tipoId : '/tipos-obrigacao';
$rotulos = [
    'nome' => 'Nome',
    'descricao' => 'Descrição',
    'esfera' => 'Esfera',
    'periodicidade' => 'Periodicidade',
    'dia_vencimento' => 'Dia de vencimento',
    'meses_apos_competencia' => 'Meses após a competência',
    'ativo' => 'Ativo',
];
$invalido = static fn (string $campo): string => isset($erros[$campo]) ? ' aria-invalid="true"' : '';
?>
<section class="pagina pagina-formulario">
    <header class="pagina-cabecalho">
        <div>
            <h1><?= e($emEdicao ? 'Editar tipo de obrigação' : 'Cadastrar tipo de obrigação') ?></h1>
            <p>O prazo é informado pelo escritório e não é conferido pelo sistema: confira o prazo vigente na fonte oficial.</p>
        </div>
        <a class="botao botao-secundario" href="<?= e(url('/tipos-obrigacao')) ?>">Cancelar</a>
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

        <div class="grade-formulario">
            <div class="campo campo-largo">
                <label for="nome">Nome <span aria-hidden="true">*</span></label>
                <input id="nome" name="nome" type="text" maxlength="100" required
                    value="<?= e($valor('nome')) ?>"<?= $invalido('nome') ?>>
                <?php $campo = 'nome'; require dirname(__DIR__, 2) . '/partials/campo-erro.php'; ?>
            </div>
            <div class="campo campo-largo">
                <label for="descricao">Descrição</label>
                <input id="descricao" name="descricao" type="text" maxlength="255"
                    value="<?= e($valor('descricao')) ?>"<?= $invalido('descricao') ?>>
                <?php $campo = 'descricao'; require dirname(__DIR__, 2) . '/partials/campo-erro.php'; ?>
            </div>
            <div class="campo">
                <label for="esfera">Esfera <span aria-hidden="true">*</span></label>
                <select id="esfera" name="esfera" required<?= $invalido('esfera') ?>>
                    <?php foreach ([
                        'FEDERAL' => 'Federal',
                        'ESTADUAL' => 'Estadual',
                        'MUNICIPAL' => 'Municipal',
                        'TRABALHISTA' => 'Trabalhista',
                        'OUTRA' => 'Outra',
                    ] as $codigo => $rotulo) : ?>
                        <option value="<?= e($codigo) ?>"<?= $valor('esfera') === $codigo ? ' selected' : '' ?>><?= e($rotulo) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php $campo = 'esfera'; require dirname(__DIR__, 2) . '/partials/campo-erro.php'; ?>
            </div>
            <div class="campo">
                <label for="periodicidade">Periodicidade <span aria-hidden="true">*</span></label>
                <select id="periodicidade" name="periodicidade" required<?= $invalido('periodicidade') ?>>
                    <?php foreach ([
                        'MENSAL' => 'Mensal',
                        'TRIMESTRAL' => 'Trimestral',
                        'ANUAL' => 'Anual',
                        'EVENTUAL' => 'Eventual',
                    ] as $codigo => $rotulo) : ?>
                        <option value="<?= e($codigo) ?>"<?= $valor('periodicidade') === $codigo ? ' selected' : '' ?>><?= e($rotulo) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php $campo = 'periodicidade'; require dirname(__DIR__, 2) . '/partials/campo-erro.php'; ?>
            </div>
            <div class="campo">
                <label for="dia_vencimento">Dia de vencimento</label>
                <input id="dia_vencimento" name="dia_vencimento" type="number" min="1" max="31"
                    value="<?= e($valor('dia_vencimento')) ?>"<?= $invalido('dia_vencimento') ?>>
                <small>Obrigatório para tipos mensais e trimestrais. Em meses mais curtos, vale o último dia.</small>
                <?php $campo = 'dia_vencimento'; require dirname(__DIR__, 2) . '/partials/campo-erro.php'; ?>
            </div>
            <div class="campo">
                <label for="meses_apos_competencia">Meses após a competência <span aria-hidden="true">*</span></label>
                <input id="meses_apos_competencia" name="meses_apos_competencia" type="number" min="0" max="12" required
                    value="<?= e($valor('meses_apos_competencia')) ?>"<?= $invalido('meses_apos_competencia') ?>>
                <?php $campo = 'meses_apos_competencia'; require dirname(__DIR__, 2) . '/partials/campo-erro.php'; ?>
            </div>
            <div class="campo">
                <label class="opcao">
                    <input type="hidden" name="ativo" value="0">
                    <input type="checkbox" name="ativo" value="1"<?= $valor('ativo') === '1' ? ' checked' : '' ?><?= $invalido('ativo') ?>>
                    Ativo (disponível para novos vínculos)
                </label>
                <?php $campo = 'ativo'; require dirname(__DIR__, 2) . '/partials/campo-erro.php'; ?>
            </div>
        </div>

        <div class="acoes-formulario">
            <button class="botao" type="submit"><?= e($emEdicao ? 'Salvar alterações' : 'Cadastrar tipo') ?></button>
        </div>
    </form>

    <?php if ($emEdicao) : ?>
        <form class="cartao acao-exclusao" method="post" action="<?= e(url('/tipos-obrigacao/' . $tipoId . '/excluir')) ?>">
            <?= csrf_campo() ?>
            <p>
                Excluir este tipo? Os vínculos deste tipo com empresas também serão removidos.
                Se já existirem obrigações deste tipo, a exclusão será bloqueada: nesse caso, inative o tipo.
            </p>
            <button class="botao botao-perigo" type="submit">Excluir tipo</button>
        </form>
    <?php endif; ?>
</section>
