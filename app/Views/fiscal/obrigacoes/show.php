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
            <a class="botao" href="<?= e(url('/obrigacoes/' . $obrigacaoId . '/editar')) ?>">Editar</a>
            <a class="botao botao-secundario" href="<?= e(url('/obrigacoes')) ?>">Voltar à lista</a>
        </div>
    </header>

    <?php if ($valor('atrasada') === '1') : ?>
        <p class="aviso aviso-erro" role="status">
            <span class="semaforo semaforo-vermelho"><span class="semaforo-ponto" aria-hidden="true"></span>Atrasada</span>:
            o vencimento já passou e a obrigação ainda está em aberto.
        </p>
    <?php elseif ($valor('entregue_com_atraso') === '1') : ?>
        <p class="aviso aviso-aviso" role="status">
            <span class="semaforo semaforo-amarelo"><span class="semaforo-ponto" aria-hidden="true"></span>Entregue com atraso</span>
        </p>
    <?php endif; ?>

    <dl class="cartao detalhes-empresa">
        <div>
            <dt>Empresa</dt>
            <dd><a href="<?= e(url('/empresas/' . $valor('empresa_id'))) ?>"><?= e($valor('empresa_nome')) ?></a></dd>
        </div>
        <div><dt>Tipo</dt><dd><?= e($valor('tipo_nome')) ?></dd></div>
        <div><dt>Competência</dt><dd><?= e(substr($competencia, 5, 2) . '/' . substr($competencia, 0, 4)) ?></dd></div>
        <div><dt>Vencimento</dt><dd><?= e(data_br($valor('vencimento'))) ?></dd></div>
        <div><dt>Status</dt><dd><?= e((string) ($rotulosStatus[$valor('status')] ?? $valor('status'))) ?></dd></div>
        <div><dt>Responsável</dt><dd><?= e($ouTraco($valor('responsavel_nome'))) ?></dd></div>
        <?php if ($entregueEm !== '') : ?>
            <div><dt>Entregue em</dt><dd><?= e(data_br(substr($entregueEm, 0, 10)) . ' ' . substr($entregueEm, 11, 5)) ?></dd></div>
        <?php endif; ?>
        <div><dt>Protocolo</dt><dd><?= e($ouTraco($valor('protocolo'))) ?></dd></div>
        <div class="detalhe-largo"><dt>Observação interna</dt><dd><?= nl2br(e($ouTraco($valor('observacao')))) ?></dd></div>
    </dl>

    <?php if ($usuarioAdmin) : ?>
        <form class="cartao acao-exclusao" method="post" action="<?= e(url('/obrigacoes/' . $obrigacaoId . '/excluir')) ?>">
            <?= csrf_campo() ?>
            <p>Excluir esta obrigação? Essa ação não poderá ser desfeita.</p>
            <button class="botao botao-perigo" type="submit">Excluir obrigação</button>
        </form>
    <?php endif; ?>
</section>
