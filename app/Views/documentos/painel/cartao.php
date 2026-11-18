<?php

declare(strict_types=1);

$aguardando = (int) ($dados['aguardando_conferencia'] ?? 0);
$atrasadas = (int) ($dados['atrasadas'] ?? 0);
?>
<div>
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <p class="texto-mutado mb-0">Status das solicitações de arquivos e documentos dos clientes.</p>
        <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('/solicitacoes/nova')) ?>">+ Nova Solicitação</a>
    </div>

    <div class="grid-kpis mb-0">
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
                <span class="kpi-subtexto"><?= $aguardando > 0 ? 'Ação pendente' : 'Nenhum documento aguardando' ?></span>
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
                <span class="kpi-subtexto"><?= $atrasadas > 0 ? 'Requer cobrança' : 'Clientes em dia' ?></span>
            </div>
        </div>
    </div>
</div>
