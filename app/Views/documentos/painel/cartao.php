<?php

declare(strict_types=1);

$aguardando = (int) ($dados['aguardando_conferencia'] ?? 0);
$atrasadas = (int) ($dados['atrasadas'] ?? 0);
?>
<section class="cartao" aria-labelledby="titulo-documentos-painel">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
        <h2 id="titulo-documentos-painel" style="margin: 0; font-size: 1.25rem;">Documentos e Portal</h2>
        <a class="botao botao-pequeno botao-secundario" href="<?= e(url('/solicitacoes/nova')) ?>">+ Nova Solicitação</a>
    </div>
    <p style="color: #64748b; font-size: 0.9rem; margin-top: 0;">Status dos pedidos de arquivos e comprovantes enviados aos clientes.</p>
    <ul>
        <li>
            Aguardando conferência do escritório:
            <a class="indicador-valor" href="<?= e(url('/solicitacoes', ['status' => 'enviada'])) ?>" style="<?= $aguardando > 0 ? 'font-weight: 700; color: #2563eb;' : '' ?>">
                <?= e((string) $aguardando) ?>
            </a>
            <?php if ($aguardando > 0) : ?>
                <span class="badge badge-info" style="margin-left: 0.35rem;">Ação pendente</span>
            <?php endif; ?>
        </li>
        <li>
            Solicitações com prazo vencido:
            <a class="indicador-valor" href="<?= e(url('/solicitacoes', ['status' => 'aberta'])) ?>" style="<?= $atrasadas > 0 ? 'font-weight: 700; color: #dc2626;' : '' ?>">
                <?= e((string) $atrasadas) ?>
            </a>
            <?php if ($atrasadas > 0) : ?>
                <span class="badge badge-perigo" style="margin-left: 0.35rem;">Atrasado</span>
            <?php endif; ?>
        </li>
    </ul>
</section>
