<?php

declare(strict_types=1);

$empresa = is_array($empresa ?? null) ? $empresa : [];
$documentos = is_array($documentos ?? null) ? $documentos : [];
$obrigacoes = is_array($obrigacoes ?? null) ? $obrigacoes : [];
$pendenciasCount = is_int($pendenciasCount ?? null) ? $pendenciasCount : 0;
$hoje = is_string($hoje ?? null) ? $hoje : date('Y-m-d');

$statusLabels = [
    'aberta' => ['rotulo' => 'Pendente de envio', 'badge' => 'badge-alerta', 'cor' => '#d97706'],
    'enviada' => ['rotulo' => 'Em conferência pelo escritório', 'badge' => 'badge-info', 'cor' => '#2563eb'],
    'aceita' => ['rotulo' => 'Recebido e conferido', 'badge' => 'badge-sucesso', 'cor' => '#16a34a'],
    'recusada' => ['rotulo' => 'Recusado (reenvio necessário)', 'badge' => 'badge-perigo', 'cor' => '#dc2626'],
];

$regimeTexto = match ((string) ($empresa['regime_tributario'] ?? $empresa['regime'] ?? '')) {
    'SIMPLES_NACIONAL' => 'Simples Nacional',
    'MEI' => 'MEI',
    'LUCRO_PRESUMIDO' => 'Lucro Presumido',
    'LUCRO_REAL' => 'Lucro Real',
    default => (string) ($empresa['regime_tributario'] ?? 'Simples Nacional'),
};

$emAnaliseCount = 0;
$conferidosCount = 0;
foreach ($documentos as $d) {
    if (($d['status'] ?? '') === 'enviada') {
        $emAnaliseCount++;
    } elseif (($d['status'] ?? '') === 'aceita') {
        $conferidosCount++;
    }
}
?>
<section class="pagina portal-cliente">
    <!-- Cabeçalho de Boas-vindas da Empresa -->
    <header class="pagina-cabecalho" style="background: #ffffff; padding: 1.25rem 1.5rem; border-radius: 0.6rem; border: 1px solid #e2e8f0; margin-bottom: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem; width: 100%;">
            <div>
                <span style="font-size: 0.8rem; font-weight: 700; color: #153e75; text-transform: uppercase; letter-spacing: 0.04em;">Painel do Cliente</span>
                <h1 style="margin: 0.25rem 0 0.4rem; font-size: clamp(1.3rem, 3vw, 1.75rem); color: #0f172a;">
                    <?= e((string) ($empresa['nome_fantasia'] ?: $empresa['razao_social'])) ?>
                </h1>
                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center; font-size: 0.85rem; color: #475569;">
                    <span style="background: #f1f5f9; padding: 0.25rem 0.5rem; border-radius: 4px; font-weight: 600;">CNPJ: <?= e(\App\Support\Cnpj::formatar((string) $empresa['cnpj'])) ?></span>
                    <span style="background: #eff6ff; color: #1d4ed8; padding: 0.25rem 0.5rem; border-radius: 4px; font-weight: 600;">Regime: <?= e($regimeTexto) ?></span>
                    <?php if (!empty($empresa['responsavel_nome'])) : ?>
                        <span style="color: #64748b;">Contador Responsável: <strong><?= e((string) $empresa['responsavel_nome']) ?></strong></span>
                    <?php endif; ?>
                </div>
            </div>
            <div>
                <a href="<?= e(url('/avaliacao')) ?>" class="botao botao-secundario" style="display: inline-flex; align-items: center; gap: 0.4rem; font-size: 0.875rem;">
                    ⭐ Avaliar o Sistema
                </a>
            </div>
        </div>
    </header>

    <!-- Grid de Indicadores Rápidos do Empresário -->
    <div class="grid-kpis" style="margin-bottom: 1.5rem;">
        <!-- Card 1: Documentos Pendentes -->
        <div class="kpi-card <?= $pendenciasCount > 0 ? 'kpi-alerta' : 'kpi-sucesso' ?>">
            <div class="kpi-icone"><?= $pendenciasCount > 0 ? '⚠️' : '✅' ?></div>
            <div class="kpi-dados">
                <span class="kpi-rotulo">Documentos Pendentes</span>
                <span class="kpi-valor" style="color: <?= $pendenciasCount > 0 ? '#b45309' : '#15803d' ?>;">
                    <?= e((string) $pendenciasCount) ?>
                </span>
                <span class="kpi-subtexto">
                    <?= $pendenciasCount > 0 ? 'Requerem envio urgente' : 'Nenhuma pendência hoje' ?>
                </span>
            </div>
        </div>

        <!-- Card 2: Em Análise -->
        <div class="kpi-card kpi-info">
            <div class="kpi-icone">📥</div>
            <div class="kpi-dados">
                <span class="kpi-rotulo">Em Conferência</span>
                <span class="kpi-valor" style="color: #1d4ed8;">
                    <?= e((string) $emAnaliseCount) ?>
                </span>
                <span class="kpi-subtexto">Aguardando validação do contador</span>
            </div>
        </div>

        <!-- Card 3: Documentos Aprovados -->
        <div class="kpi-card kpi-sucesso">
            <div class="kpi-icone">📁</div>
            <div class="kpi-dados">
                <span class="kpi-rotulo">Documentos Aceitos</span>
                <span class="kpi-valor" style="color: #15803d;">
                    <?= e((string) $conferidosCount) ?>
                </span>
                <span class="kpi-subtexto">Arquivos aprovados neste ciclo</span>
            </div>
        </div>

        <!-- Card 4: Guias a Vencer -->
        <div class="kpi-card">
            <div class="kpi-icone">📅</div>
            <div class="kpi-dados">
                <span class="kpi-rotulo">Guias da Empresa</span>
                <span class="kpi-valor" style="color: #0f172a;">
                    <?= e((string) count($obrigacoes)) ?>
                </span>
                <span class="kpi-subtexto">Tributos e rotinas acompanhados</span>
            </div>
        </div>
    </div>

    <!-- Bloco 1: Solicitações de Documentos (Envio Interativo) -->
    <div class="cartao" style="margin-bottom: 1.75rem;">
        <div class="cartao-cabecalho-flex" style="border-bottom: 1px solid #e2e8f0; padding-bottom: 0.75rem; margin-bottom: 1.25rem;">
            <div>
                <h2 style="font-size: 1.25rem; margin: 0 0 0.25rem; color: #0f172a;">Documentos Solicitados pelo Escritório</h2>
                <p class="texto-mutado" style="margin: 0;">Envie os comprovantes e extratos solicitados pela contabilidade sem precisar usar o WhatsApp.</p>
            </div>
            <span class="badge <?= $pendenciasCount > 0 ? 'badge-alerta' : 'badge-sucesso' ?>" style="font-size: 0.85rem; padding: 0.35rem 0.65rem;">
                <?= $pendenciasCount > 0 ? e((string) $pendenciasCount) . ' a enviar' : '100% em dia' ?>
            </span>
        </div>

        <?php if ($documentos === []) : ?>
            <div style="text-align: center; padding: 2.5rem 1rem; color: #64748b;">
                <p style="font-size: 2rem; margin: 0 0 0.5rem;">🎉</p>
                <p style="font-weight: 600; color: #1e293b; margin: 0 0 0.25rem;">Nenhum documento solicitado no momento.</p>
                <p style="font-size: 0.9rem; margin: 0;">Quando o escritório solicitar extratos ou comprovantes, eles aparecerão aqui para envio direto.</p>
            </div>
        <?php else : ?>
            <div style="display: flex; flex-direction: column; gap: 1.25rem;">
                <?php foreach ($documentos as $doc) : ?>
                    <?php
                        $st = (string) $doc['status'];
                        $info = $statusLabels[$st] ?? ['rotulo' => $st, 'badge' => '', 'cor' => '#64748b'];
                        $prazoStr = date('d/m/Y', strtotime((string) $doc['prazo']));
                        $atrasado = (int) ($doc['atrasada'] ?? 0) === 1;
                        $precisaEnviar = in_array($st, ['aberta', 'recusada'], true);
                    ?>
                    <div style="background: #ffffff; border: 1px solid <?= $atrasado ? '#fca5a5' : '#cbd5e1' ?>; border-left: 5px solid <?= e($info['cor']) ?>; border-radius: 8px; padding: 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 0.75rem; margin-bottom: 0.75rem;">
                            <div style="flex: 1; min-width: 240px;">
                                <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                                    <h3 style="margin: 0; font-size: 1.1rem; font-weight: 700; color: #0f172a;">
                                        <?= e((string) $doc['titulo']) ?>
                                    </h3>
                                    <span class="badge <?= e($info['badge']) ?>" style="font-size: 0.78rem;">
                                        <?= e($info['rotulo']) ?>
                                    </span>
                                </div>
                                <div style="margin-top: 0.35rem; font-size: 0.85rem; color: #64748b; display: flex; align-items: center; gap: 0.4rem;">
                                    <span>Prazo limite: <strong style="<?= $atrasado ? 'color: #dc2626;' : '#0f172a' ?>"><?= e($prazoStr) ?></strong></span>
                                    <?php if ($atrasado) : ?>
                                        <span class="badge badge-perigo" style="font-size: 0.72rem;">Prazo Vencido</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <?php if (!empty($doc['descricao'])) : ?>
                            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 0.85rem; margin-bottom: 0.85rem; font-size: 0.9rem; color: #334155;">
                                <div style="font-weight: 600; color: #1e293b; margin-bottom: 0.25rem;">💡 Orientações do contador:</div>
                                <?= e((string) $doc['descricao']) ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($doc['motivo_recusa'])) : ?>
                            <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 6px; padding: 0.85rem; margin-bottom: 0.85rem; font-size: 0.9rem; color: #991b1b;">
                                <div style="font-weight: 700; display: flex; align-items: center; gap: 0.35rem; margin-bottom: 0.25rem;">
                                    ⚠️ Motivo da recusa pelo contador:
                                </div>
                                <?= e((string) $doc['motivo_recusa']) ?>
                                <div style="margin-top: 0.35rem; font-size: 0.8rem; color: #b91c1c;">Por favor, anexe e envie um novo arquivo corrigido abaixo.</div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($doc['arquivo_path'])) : ?>
                            <div style="margin-bottom: 0.85rem; padding: 0.6rem 0.85rem; background: #f1f5f9; border-radius: 6px; font-size: 0.875rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.5rem;">
                                <div>
                                    <span style="color: #475569;">Arquivo anexado:</span>
                                    <strong style="color: #0f172a; margin-left: 0.25rem;"><?= e((string) $doc['arquivo_nome_original']) ?></strong>
                                    <?php if (!empty($doc['enviado_em'])) : ?>
                                        <span style="color: #64748b; font-size: 0.8rem; margin-left: 0.4rem;">(enviado em <?= e(date('d/m/Y H:i', strtotime((string) $doc['enviado_em']))) ?>)</span>
                                    <?php endif; ?>
                                </div>
                                <a href="<?= e(url('/portal/documentos/' . $doc['id'] . '/download')) ?>" class="botao botao-pequeno botao-secundario" style="display: inline-flex; align-items: center; gap: 0.35rem;">
                                    📥 Baixar arquivo
                                </a>
                            </div>
                        <?php endif; ?>

                        <?php if ($precisaEnviar) : ?>
                            <form method="post" action="<?= e(url('/portal/documentos/' . $doc['id'] . '/enviar')) ?>" enctype="multipart/form-data" style="margin-top: 0.75rem; background: #f8fafc; padding: 1rem; border-radius: 6px; border: 1.5px dashed #94a3b8;">
                                <?= csrf_campo() ?>
                                <label for="arquivo_<?= e((string) $doc['id']) ?>" style="display: block; font-weight: 700; font-size: 0.9rem; color: #1e293b; margin-bottom: 0.5rem;">
                                    <?= ($st === 'recusada') ? '📤 Selecionar novo arquivo corrigido:' : '📤 Selecionar arquivo para envio:' ?>
                                </label>
                                <div style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
                                    <input type="file" id="arquivo_<?= e((string) $doc['id']) ?>" name="arquivo" accept=".pdf,.jpg,.jpeg,.png,.xml" required style="font-size: 0.88rem; flex: 1; min-width: 200px;">
                                    <button type="submit" class="botao botao-primario" style="white-space: nowrap;">
                                        Enviar Documento
                                    </button>
                                </div>
                                <small style="display: block; margin-top: 0.5rem; color: #64748b; font-size: 0.78rem;">
                                    Formatos aceitos: PDF, JPG, PNG e XML (Tamanho máximo permitido: 5 MB).
                                </small>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Bloco 2: Próximas Guias e Obrigações Fiscais da Empresa -->
    <div class="cartao">
        <div class="cartao-cabecalho-flex" style="border-bottom: 1px solid #e2e8f0; padding-bottom: 0.75rem; margin-bottom: 1.25rem;">
            <div>
                <h2 style="font-size: 1.25rem; margin: 0 0 0.25rem; color: #0f172a;">Próximas Guias e Obrigações da Empresa</h2>
                <p class="texto-mutado" style="margin: 0;">Acompanhe as guias e declarações geradas pela sua contabilidade para pagamento e conformidade.</p>
            </div>
        </div>

        <?php if ($obrigacoes === []) : ?>
            <p style="color: #64748b; text-align: center; padding: 2rem 0;">Nenhuma obrigação registrada para esta empresa.</p>
        <?php else : ?>
            <div class="tabela-responsiva">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">Obrigação / Guia</th>
                            <th scope="col">Competência</th>
                            <th scope="col">Vencimento</th>
                            <th scope="col">Valor</th>
                            <th scope="col">Situação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($obrigacoes as $ob) : ?>
                            <?php
                                $vencimento = (string) $ob['vencimento'];
                                $vencida = ((string) $ob['status'] !== 'ENTREGUE') && ($vencimento < $hoje);
                                $entregue = ((string) $ob['status'] === 'ENTREGUE');
                                $valorCentavos = $ob['valor_centavos'] ?? null;
                            ?>
                            <tr>
                                <td>
                                    <strong><?= e((string) $ob['tipo_nome']) ?></strong>
                                    <?php if (!empty($ob['observacao'])) : ?>
                                        <div style="font-size: 0.8rem; color: #64748b;"><?= e((string) $ob['observacao']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?= e((string) $ob['competencia']) ?></td>
                                <td>
                                    <strong><?= e(date('d/m/Y', strtotime($vencimento))) ?></strong>
                                    <?php if ($vencida) : ?>
                                        <br><span class="badge badge-perigo" style="font-size: 0.72rem;">Vencida</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($valorCentavos !== null && is_numeric($valorCentavos)) : ?>
                                        <strong><?= e('R$ ' . number_format(((int) $valorCentavos) / 100, 2, ',', '.')) ?></strong>
                                    <?php else : ?>
                                        <span style="color: #94a3b8;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($entregue) : ?>
                                        <span class="badge badge-sucesso">Entregue / Paga</span>
                                    <?php elseif ($vencida) : ?>
                                        <span class="badge badge-perigo">Pendente Vencida</span>
                                    <?php else : ?>
                                        <span class="badge badge-alerta">A Vencer</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>
