<?php

declare(strict_types=1);

use App\Core\UsuarioLogado;

$obrigacao = is_array($obrigacao ?? null) ? $obrigacao : [];
$rotulosStatus = is_array($rotulosStatus ?? null) ? $rotulosStatus : [];
$usuarioAdmin = ($usuario ?? null) instanceof UsuarioLogado && $usuario->ehAdmin();
$obrigacaoId = (int) ($obrigacao['id'] ?? 0);
$valor = static fn (string $campo): string => is_scalar($obrigacao[$campo] ?? null)
    ? (string) $obrigacao[$campo]
    : '';
$ouTraco = static fn (string $texto): string => $texto !== '' ? $texto : '—';
$competencia = $valor('competencia');
$entregueEm = $valor('entregue_em');
?>
<section class="pagina">
    <header class="pagina-cabecalho">
        <div>
            <h1><?= e($valor('tipo_nome')) ?></h1>
            <p><?= e($valor('empresa_nome')) ?> · competência <?= e(substr($competencia, 5, 2) . '/' . substr($competencia, 0, 4)) ?></p>
        </div>
        <div class="acoes-cabecalho">
            <a class="btn btn-primary" href="<?= e(url('/obrigacoes/' . $obrigacaoId . '/editar')) ?>">
                <?php $icone = 'editar'; require dirname(__DIR__, 2) . '/partials/icone.php'; ?>
                Editar
            </a>
            <a class="btn btn-outline-secondary" href="<?= e(url('/obrigacoes')) ?>">
                <?php $icone = 'voltar'; require dirname(__DIR__, 2) . '/partials/icone.php'; ?>
                Voltar à lista
            </a>
        </div>
    </header>

    <?php if ($valor('atraso_critico') === '1') : ?>
        <p class="alert alert-danger" role="status">
            <span class="semaforo semaforo-vermelho"><span class="semaforo-ponto" aria-hidden="true"></span>Atraso crítico (&gt; 30 dias)</span>:
            o vencimento já passou há mais de 30 dias e a obrigação continua em aberto. Risco de cancelamento de certidão e multas.
        </p>
    <?php elseif ($valor('atrasada') === '1') : ?>
        <p class="alert alert-danger" role="status">
            <span class="semaforo semaforo-vermelho"><span class="semaforo-ponto" aria-hidden="true"></span>Atrasada</span>:
            o vencimento já passou e a obrigação ainda está em aberto.
        </p>
    <?php elseif ($valor('entregue_com_atraso') === '1') : ?>
        <p class="alert alert-warning" role="status">
            <span class="semaforo semaforo-amarelo"><span class="semaforo-ponto" aria-hidden="true"></span>Entregue com atraso</span>
        </p>
    <?php endif; ?>

    <dl class="card card-body detalhes-empresa">
        <div>
            <dt>Empresa</dt>
            <dd><a href="<?= e(url('/empresas/' . $valor('empresa_id'))) ?>"><?= e($valor('empresa_nome')) ?></a></dd>
        </div>
        <div><dt>Tipo</dt><dd><?= e($valor('tipo_nome')) ?></dd></div>
        <div><dt>Competência</dt><dd><?= e(substr($competencia, 5, 2) . '/' . substr($competencia, 0, 4)) ?></dd></div>
        <div><dt>Vencimento</dt><dd><?= e(data_br($valor('vencimento'))) ?></dd></div>
        <div>
            <dt>Status</dt>
            <dd>
                <?php
                $seloRotulo = (string) ($rotulosStatus[$valor('status')] ?? $valor('status'));
                $seloTom = ['ENTREGUE' => 'sucesso', 'EM_ANDAMENTO' => 'info', 'AGUARDANDO_CLIENTE' => 'aviso'][$valor('status')] ?? 'neutro';
                require dirname(__DIR__, 2) . '/partials/status.php';
                ?>
            </dd>
        </div>
        <div><dt>Responsável</dt><dd><?= e($ouTraco($valor('responsavel_nome'))) ?></dd></div>
        <?php if ($entregueEm !== '') : ?>
            <div><dt>Entregue em</dt><dd><?= e(data_br(substr($entregueEm, 0, 10)) . ' ' . substr($entregueEm, 11, 5)) ?></dd></div>
        <?php endif; ?>
        <div><dt>Protocolo</dt><dd><?= e($ouTraco($valor('protocolo'))) ?></dd></div>
        <div class="detalhe-largo"><dt>Observação interna</dt><dd><?= nl2br(e($ouTraco($valor('observacao')))) ?></dd></div>
    </dl>

    <?php require __DIR__ . '/_form-status.php'; ?>

    <?php require __DIR__ . '/_historico.php'; ?>

    <?php if ($usuarioAdmin) : ?>
        <form class="card card-body acao-exclusao" method="post" action="<?= e(url('/obrigacoes/' . $obrigacaoId . '/excluir')) ?>"
              data-confirmar="Excluir esta obrigação? Essa ação não poderá ser desfeita.">
            <?= csrf_campo() ?>
            <p>Excluir esta obrigação? Essa ação não poderá ser desfeita.</p>
            <button class="btn btn-outline-danger" type="submit">
                <?php $icone = 'excluir'; require dirname(__DIR__, 2) . '/partials/icone.php'; ?>
                Excluir obrigação
            </button>
        </form>
    <?php endif; ?>
</section>
