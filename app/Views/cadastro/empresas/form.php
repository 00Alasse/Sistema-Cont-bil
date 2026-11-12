<?php

declare(strict_types=1);

$dados = is_array($dados ?? null) ? $dados : [];
$erros = is_array($erros ?? null) ? $erros : [];
$responsaveis = is_array($responsaveis ?? null) ? $responsaveis : [];
$empresaId = is_int($empresaId ?? null) ? $empresaId : null;
$emEdicao = $empresaId !== null;
$valor = static function (string $campo) use ($dados): string {
    $valor = $dados[$campo] ?? '';

    return is_scalar($valor) ? (string) $valor : '';
};
$acao = $emEdicao ? '/empresas/' . $empresaId : '/empresas';
?>
<section class="pagina pagina-formulario">
    <header class="pagina-cabecalho">
        <div>
            <h1><?= e($emEdicao ? 'Editar empresa' : 'Cadastrar empresa') ?></h1>
            <p>Preencha os dados cadastrais da empresa.</p>
        </div>
        <a class="botao botao-secundario" href="<?= e($emEdicao ? url('/empresas/' . $empresaId) : url('/empresas')) ?>">
            Cancelar
        </a>
    </header>

    <form class="cartao formulario" method="post" action="<?= e(url($acao)) ?>">
        <?= csrf_campo() ?>
        <?php if ($emEdicao) : ?>
            <input type="hidden" name="versao" value="<?= e($valor('versao')) ?>">
        <?php endif; ?>
        <?php if ($erros !== []) : ?>
            <?php
            $rotulosErros = [
                'razao_social' => 'Razão social',
                'nome_fantasia' => 'Nome fantasia',
                'cnpj' => 'CNPJ',
                'regime_tributario' => 'Regime tributário',
                'segmento' => 'Segmento',
                'contato_nome' => 'Nome do contato',
                'email' => 'E-mail',
                'telefone' => 'Telefone',
                'responsavel_id' => 'Responsável',
                'bpo_ativo' => 'BPO ativo',
                'consultivo_ativo' => 'Consultivo ativo',
                'observacoes' => 'Observações',
                'versao' => 'Versão',
            ];
            ?>
            <div class="aviso aviso-erro" role="alert">
                <p>Revise os campos indicados:</p>
                <ul>
                    <?php foreach ($erros as $campoErro => $mensagensErro) : ?>
                        <?php foreach (is_array($mensagensErro) ? $mensagensErro : [] as $mensagemErro) : ?>
                            <?php if (is_string($mensagemErro)) : ?>
                                <li>
                                    <strong><?= e($rotulosErros[$campoErro] ?? $campoErro) ?>:</strong>
                                    <?= e($mensagemErro) ?>
                                </li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="grade-formulario">
            <div class="campo campo-largo">
                <label for="razao_social">Razão social <span aria-hidden="true">*</span></label>
                <input id="razao_social" name="razao_social" type="text" maxlength="150" required
                    value="<?= e($valor('razao_social')) ?>"<?= isset($erros['razao_social']) ? ' aria-invalid="true"' : '' ?>>
                <?php $campo = 'razao_social'; require dirname(__DIR__, 2) . '/partials/campo-erro.php'; ?>
            </div>
            <div class="campo">
                <label for="nome_fantasia">Nome fantasia</label>
                <input id="nome_fantasia" name="nome_fantasia" type="text" maxlength="150"
                    value="<?= e($valor('nome_fantasia')) ?>"<?= isset($erros['nome_fantasia']) ? ' aria-invalid="true"' : '' ?>>
                <?php $campo = 'nome_fantasia'; require dirname(__DIR__, 2) . '/partials/campo-erro.php'; ?>
            </div>
            <div class="campo">
                <label for="cnpj">CNPJ <span aria-hidden="true">*</span></label>
                <input id="cnpj" name="cnpj" type="text" maxlength="18" required
                    value="<?= e($valor('cnpj')) ?>"<?= isset($erros['cnpj']) ? ' aria-invalid="true"' : '' ?>>
                <?php $campo = 'cnpj'; require dirname(__DIR__, 2) . '/partials/campo-erro.php'; ?>
            </div>
            <div class="campo">
                <label for="regime_tributario">Regime tributário <span aria-hidden="true">*</span></label>
                <select id="regime_tributario" name="regime_tributario" required
                    <?= isset($erros['regime_tributario']) ? 'aria-invalid="true"' : '' ?>>
                    <?php foreach ([
                        'MEI' => 'MEI',
                        'SIMPLES_NACIONAL' => 'Simples Nacional',
                        'LUCRO_PRESUMIDO' => 'Lucro Presumido',
                        'LUCRO_REAL' => 'Lucro Real',
                    ] as $codigo => $rotulo) : ?>
                        <option value="<?= e($codigo) ?>"<?= $valor('regime_tributario') === $codigo ? ' selected' : '' ?>>
                            <?= e($rotulo) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php $campo = 'regime_tributario'; require dirname(__DIR__, 2) . '/partials/campo-erro.php'; ?>
            </div>
            <div class="campo">
                <label for="segmento">Segmento</label>
                <input id="segmento" name="segmento" type="text" maxlength="80"
                    value="<?= e($valor('segmento')) ?>"<?= isset($erros['segmento']) ? ' aria-invalid="true"' : '' ?>>
                <?php $campo = 'segmento'; require dirname(__DIR__, 2) . '/partials/campo-erro.php'; ?>
            </div>
            <div class="campo">
                <label for="contato_nome">Nome do contato</label>
                <input id="contato_nome" name="contato_nome" type="text" maxlength="120"
                    value="<?= e($valor('contato_nome')) ?>"<?= isset($erros['contato_nome']) ? ' aria-invalid="true"' : '' ?>>
                <?php $campo = 'contato_nome'; require dirname(__DIR__, 2) . '/partials/campo-erro.php'; ?>
            </div>
            <div class="campo">
                <label for="email">E-mail</label>
                <input id="email" name="email" type="email" maxlength="150"
                    value="<?= e($valor('email')) ?>"<?= isset($erros['email']) ? ' aria-invalid="true"' : '' ?>>
                <?php $campo = 'email'; require dirname(__DIR__, 2) . '/partials/campo-erro.php'; ?>
            </div>
            <div class="campo">
                <label for="telefone">Telefone</label>
                <input id="telefone" name="telefone" type="tel" maxlength="20"
                    value="<?= e($valor('telefone')) ?>"<?= isset($erros['telefone']) ? ' aria-invalid="true"' : '' ?>>
                <?php $campo = 'telefone'; require dirname(__DIR__, 2) . '/partials/campo-erro.php'; ?>
            </div>
            <div class="campo">
                <label for="responsavel_id">Responsável</label>
                <select id="responsavel_id" name="responsavel_id"
                    <?= isset($erros['responsavel_id']) ? 'aria-invalid="true"' : '' ?>>
                    <option value="">Sem responsável</option>
                    <?php foreach ($responsaveis as $responsavel) : ?>
                        <?php if (is_array($responsavel)) : ?>
                            <?php $idResponsavel = (string) ($responsavel['id'] ?? ''); ?>
                            <option value="<?= e($idResponsavel) ?>"<?= $valor('responsavel_id') === $idResponsavel ? ' selected' : '' ?>>
                                <?= e(is_string($responsavel['nome'] ?? null) ? $responsavel['nome'] : '') ?>
                            </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
                <?php $campo = 'responsavel_id'; require dirname(__DIR__, 2) . '/partials/campo-erro.php'; ?>
            </div>
            <fieldset class="campo campo-largo">
                <legend>Serviços ativos</legend>
                <div class="opcoes-inline">
                    <label class="opcao">
                        <input type="hidden" name="bpo_ativo" value="0">
                        <input type="checkbox" name="bpo_ativo" value="1"<?= $valor('bpo_ativo') === '1' ? ' checked' : '' ?>
                            <?= isset($erros['bpo_ativo']) ? 'aria-invalid="true"' : '' ?>>
                        BPO financeiro
                    </label>
                    <label class="opcao">
                        <input type="hidden" name="consultivo_ativo" value="0">
                        <input type="checkbox" name="consultivo_ativo" value="1"<?= $valor('consultivo_ativo') === '1' ? ' checked' : '' ?>
                            <?= isset($erros['consultivo_ativo']) ? 'aria-invalid="true"' : '' ?>>
                        Consultivo
                    </label>
                </div>
                <?php $campo = 'bpo_ativo'; require dirname(__DIR__, 2) . '/partials/campo-erro.php'; ?>
                <?php $campo = 'consultivo_ativo'; require dirname(__DIR__, 2) . '/partials/campo-erro.php'; ?>
            </fieldset>
            <div class="campo campo-largo">
                <label for="observacoes">Observações</label>
                <textarea id="observacoes" name="observacoes" rows="5"
                    <?= isset($erros['observacoes']) ? 'aria-invalid="true"' : '' ?>><?= e($valor('observacoes')) ?></textarea>
                <?php $campo = 'observacoes'; require dirname(__DIR__, 2) . '/partials/campo-erro.php'; ?>
            </div>
        </div>

        <div class="acoes-formulario">
            <button class="botao" type="submit"><?= e($emEdicao ? 'Salvar alterações' : 'Cadastrar empresa') ?></button>
        </div>
    </form>
</section>
