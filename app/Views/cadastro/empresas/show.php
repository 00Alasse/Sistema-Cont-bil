<?php

declare(strict_types=1);

use App\Core\UsuarioLogado;
use App\Support\Cnpj;

$empresa = is_array($empresa ?? null) ? $empresa : [];
$usuario = $usuario ?? null;
$empresaId = (int) ($empresa['id'] ?? 0);
$valor = static fn (string $campo): string => is_scalar($empresa[$campo] ?? null)
    ? (string) $empresa[$campo]
    : '';
$cnpj = $valor('cnpj');
$regimes = ['MEI' => 'MEI', 'SIMPLES_NACIONAL' => 'Simples Nacional', 'LUCRO_PRESUMIDO' => 'Lucro Presumido', 'LUCRO_REAL' => 'Lucro Real'];
$usuarioAdmin = $usuario instanceof UsuarioLogado && $usuario->ehAdmin();
?>
<section class="pagina">
    <header class="pagina-cabecalho">
        <div>
            <h1><?= e($valor('razao_social')) ?></h1>
            <p>Detalhes cadastrais da empresa.</p>
        </div>
        <div class="acoes-cabecalho">
            <a class="botao" href="<?= e(url('/empresas/' . $empresaId . '/editar')) ?>">
                <?php $icone = 'editar'; require dirname(__DIR__, 2) . '/partials/icone.php'; ?>
                Editar
            </a>
            <a class="botao botao-secundario" href="<?= e(url('/empresas')) ?>">
                <?php $icone = 'voltar'; require dirname(__DIR__, 2) . '/partials/icone.php'; ?>
                Voltar à lista
            </a>
        </div>
    </header>

    <?php
    require dirname(__DIR__, 2) . '/partials/abas-empresa.php';
    ?>

    <dl class="cartao detalhes-empresa">
        <div><dt>Nome fantasia</dt><dd><?= e($valor('nome_fantasia') !== '' ? $valor('nome_fantasia') : '—') ?></dd></div>
        <div><dt>CNPJ</dt><dd><?= e(Cnpj::formatar($cnpj)) ?></dd></div>
        <div><dt>Regime tributário</dt><dd><?= e($regimes[$valor('regime_tributario')] ?? $valor('regime_tributario')) ?></dd></div>
        <div><dt>Segmento</dt><dd><?= e($valor('segmento') !== '' ? $valor('segmento') : '—') ?></dd></div>
        <div><dt>Contato</dt><dd><?= e($valor('contato_nome') !== '' ? $valor('contato_nome') : '—') ?></dd></div>
        <div><dt>E-mail</dt><dd><?= e($valor('email') !== '' ? $valor('email') : '—') ?></dd></div>
        <div><dt>Telefone</dt><dd><?= e($valor('telefone') !== '' ? $valor('telefone') : '—') ?></dd></div>
        <div><dt>Responsável</dt><dd><?= e($valor('responsavel_nome') !== '' ? $valor('responsavel_nome') : '—') ?></dd></div>
        <div><dt>BPO financeiro</dt><dd><?= e($valor('bpo_ativo') === '1' ? 'Ativo' : 'Inativo') ?></dd></div>
        <div><dt>Consultivo</dt><dd><?= e($valor('consultivo_ativo') === '1' ? 'Ativo' : 'Inativo') ?></dd></div>
        <div><dt>Status</dt><dd>
                <?php
                $seloRotulo = $valor('status') === 'ATIVA' ? 'Ativa' : 'Inativa';
                $seloTom = $valor('status') === 'ATIVA' ? 'sucesso' : 'neutro';
                require dirname(__DIR__, 2) . '/partials/status.php';
                ?>
            </dd></div>
        <div class="detalhe-largo"><dt>Observações</dt><dd><?= $valor('observacoes') !== '' ? nl2br(e($valor('observacoes'))) : '—' ?></dd></div>
    </dl>

    <?php if ($usuarioAdmin) : ?>
        <form class="cartao acao-exclusao" method="post" action="<?= e(url('/empresas/' . $empresaId . '/excluir')) ?>"
              data-confirmar="Excluir esta empresa? Essa ação não poderá ser desfeita.">
            <?= csrf_campo() ?>
            <p>Excluir esta empresa? Essa ação não poderá ser desfeita.</p>
            <button class="botao botao-perigo" type="submit">
                <?php $icone = 'excluir'; require dirname(__DIR__, 2) . '/partials/icone.php'; ?>
                Excluir empresa
            </button>
        </form>
    <?php endif; ?>
</section>
