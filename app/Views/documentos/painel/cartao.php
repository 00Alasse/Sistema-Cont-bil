<?php

declare(strict_types=1);

$aguardando = (int) ($dados['aguardando_conferencia'] ?? 0);
$atrasadas = (int) ($dados['atrasadas'] ?? 0);
?>
<div>
    <div class="est-cdd8b4">
        <p class="texto-mutado est-ff227d">Status das solicitações de arquivos e documentos dos clientes.</p>
        <a class="botao botao-pequeno botao-secundario" href="<?= e(url('/solicitacoes/nova')) ?>">+ Nova Solicitação</a>
    </div>

    <div class="grid-kpis est-0a0522">
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
                    <span class="badge badge-info est-fd96e6">Ação pendente</span>
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
                    <span class="badge badge-perigo est-fd96e6">Requer cobrança</span>
                <?php else : ?>
                    <span class="kpi-subtexto">Clientes em dia</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
