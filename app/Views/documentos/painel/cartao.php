<?php

declare(strict_types=1);

$aguardando = (int) ($dados['aguardando_conferencia'] ?? 0);
$atrasadas = (int) ($dados['atrasadas'] ?? 0);
?>
<div>
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 0.5rem;">
        <p class="texto-mutado" style="margin: 0;">Status das solicitações de arquivos e documentos dos clientes.</p>
        <a class="botao botao-pequeno botao-secundario" href="<?= e(url('/solicitacoes/nova')) ?>">+ Nova Solicitação</a>
    </div>

    <div class="grid-kpis" style="grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); margin-bottom: 0;">
        <!-- Aguardando conferência -->
        <div class="kpi-card kpi-info">
            <div class="kpi-icone">📥</div>
            <div class="kpi-dados">
                <span class="kpi-rotulo">Aguardando conferência</span>
                <span class="kpi-valor">
                    <a class="indicador-valor" href="<?= e(url('/solicitacoes', ['status' => 'enviada'])) ?>">
                        <?= e((string) $aguardando) ?>
                    </a>
                </span>
                <?php if ($aguardando > 0) : ?>
                    <span class="badge badge-info" style="align-self: flex-start; margin-top: 0.35rem;">Ação pendente</span>
                <?php else : ?>
                    <span class="kpi-subtexto">Nenhum documento aguardando</span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Solicitações atrasadas -->
        <div class="kpi-card kpi-perigo">
            <div class="kpi-icone">⏰</div>
            <div class="kpi-dados">
                <span class="kpi-rotulo">Prazo de envio vencido</span>
                <span class="kpi-valor">
                    <a class="indicador-valor" href="<?= e(url('/solicitacoes', ['status' => 'aberta'])) ?>">
                        <?= e((string) $atrasadas) ?>
                    </a>
                </span>
                <?php if ($atrasadas > 0) : ?>
                    <span class="badge badge-perigo" style="align-self: flex-start; margin-top: 0.35rem;">Requer cobrança</span>
                <?php else : ?>
                    <span class="kpi-subtexto">Clientes em dia</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
