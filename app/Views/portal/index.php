<?php

declare(strict_types=1);

$empresa = is_array($empresa ?? null) ? $empresa : [];
$documentos = is_array($documentos ?? null) ? $documentos : [];
$obrigacoes = is_array($obrigacoes ?? null) ? $obrigacoes : [];
$pendenciasCount = is_int($pendenciasCount ?? null) ? $pendenciasCount : 0;
$hoje = is_string($hoje ?? null) ? $hoje : date('Y-m-d');

// "tom" alimenta o selo (partials/status.php); "faixa" é a classe da faixa colorida do cartão do documento.
$statusLabels = [
    'aberta' => ['rotulo' => 'Pendente de envio', 'tom' => 'aviso', 'faixa' => 'documento-aberta'],
    'enviada' => ['rotulo' => 'Em conferência pelo escritório', 'tom' => 'info', 'faixa' => 'documento-enviada'],
    'aceita' => ['rotulo' => 'Recebido e conferido', 'tom' => 'sucesso', 'faixa' => 'documento-aceita'],
    'recusada' => ['rotulo' => 'Recusado (reenvio necessário)', 'tom' => 'perigo', 'faixa' => 'documento-recusada'],
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
    <header class="card card-body mb-4">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
            <div>
                <span class="small fw-bold text-uppercase text-primary">Painel do Cliente</span>
                <h1 class="fs-3 mt-1 mb-2">
                    <?= e((string) ($empresa['nome_fantasia'] ?: $empresa['razao_social'])) ?>
                </h1>
                <div class="d-flex flex-wrap align-items-center gap-2 small text-body-secondary">
                    <span class="badge text-bg-light border">CNPJ: <?= e(\App\Support\Cnpj::formatar((string) $empresa['cnpj'])) ?></span>
                    <span class="badge bg-primary-subtle text-primary-emphasis">Regime: <?= e($regimeTexto) ?></span>
                    <?php if (!empty($empresa['responsavel_nome'])) : ?>
                        <span>Contador Responsável: <strong><?= e((string) $empresa['responsavel_nome']) ?></strong></span>
                    <?php endif; ?>
                </div>
            </div>
            <div>
                <a href="<?= e(url('/avaliacao')) ?>" class="btn btn-outline-secondary btn-sm">
                    ⭐ Avaliar o Sistema
                </a>
            </div>
        </div>
    </header>

    <!-- Grid de Indicadores Rápidos do Empresário (Filtros Clicáveis) -->
    <div class="grid-kpis grid-kpis-interativos">
        <!-- Card 1: Documentos Pendentes -->
        <div class="kpi-card <?= $pendenciasCount > 0 ? 'kpi-alerta' : 'kpi-sucesso' ?>" role="button" tabindex="0" data-filtro-portal="pendentes" title="Clique para filtrar apenas documentos pendentes de envio">
            <div class="kpi-icone"><?= $pendenciasCount > 0 ? '⚠️' : '✅' ?></div>
            <div class="kpi-dados">
                <span class="kpi-rotulo">Documentos Pendentes</span>
                <span class="kpi-valor <?= $pendenciasCount > 0 ? 'text-warning-emphasis' : 'text-success-emphasis' ?>">
                    <?= e((string) $pendenciasCount) ?>
                </span>
                <span class="kpi-subtexto">
                    <?= $pendenciasCount > 0 ? 'Requerem envio urgente' : 'Nenhuma pendência hoje' ?>
                </span>
            </div>
        </div>

        <!-- Card 2: Em Análise -->
        <div class="kpi-card kpi-info" role="button" tabindex="0" data-filtro-portal="conferencia" title="Clique para filtrar documentos em conferência pelo escritório">
            <div class="kpi-icone">📥</div>
            <div class="kpi-dados">
                <span class="kpi-rotulo">Em Conferência</span>
                <span class="kpi-valor text-primary-emphasis">
                    <?= e((string) $emAnaliseCount) ?>
                </span>
                <span class="kpi-subtexto">Aguardando validação do contador</span>
            </div>
        </div>

        <!-- Card 3: Documentos Aprovados -->
        <div class="kpi-card kpi-sucesso" role="button" tabindex="0" data-filtro-portal="aceitos" title="Clique para filtrar documentos aceitos">
            <div class="kpi-icone">📁</div>
            <div class="kpi-dados">
                <span class="kpi-rotulo">Documentos Aceitos</span>
                <span class="kpi-valor text-success-emphasis">
                    <?= e((string) $conferidosCount) ?>
                </span>
                <span class="kpi-subtexto">Arquivos aprovados neste ciclo</span>
            </div>
        </div>

        <!-- Card 4: Guias a Vencer -->
        <div class="kpi-card" role="button" tabindex="0" data-filtro-portal="guias" title="Clique para ver apenas as guias e tributos">
            <div class="kpi-icone">📅</div>
            <div class="kpi-dados">
                <span class="kpi-rotulo">Guias da Empresa</span>
                <span class="kpi-valor">
                    <?= e((string) count($obrigacoes)) ?>
                </span>
                <span class="kpi-subtexto">Tributos e rotinas acompanhados</span>
            </div>
        </div>
    </div>

    <!-- Barra de Filtro Ativo -->
    <div id="barra-filtro-portal" class="barra-filtro-ativo filtro-oculto">
        <div class="filtro-info-texto">
            <span class="filtro-rotulo-icone">🔍</span>
            <span id="texto-filtro-ativo">Filtrando conteúdo</span>
        </div>
        <button type="button" id="botao-limpar-filtro" class="botao-limpar-filtro">
            ✕ Mostrar tudo
        </button>
    </div>

    <!-- Bloco 1: Solicitações de Documentos (Envio Interativo) -->
    <div id="secao-documentos-portal" class="card card-body mb-4">
        <div class="cartao-cabecalho-flex border-bottom pb-3 mb-4">
            <div>
                <h2 class="h5 mb-1">Documentos Solicitados pelo Escritório</h2>
                <p class="texto-mutado mb-0">Envie os comprovantes e extratos solicitados pela contabilidade sem precisar usar o WhatsApp.</p>
            </div>
            <?php
            $seloRotulo = $pendenciasCount > 0 ? $pendenciasCount . ' a enviar' : '100% em dia';
            $seloTom = $pendenciasCount > 0 ? 'aviso' : 'sucesso';
            require dirname(__DIR__) . '/partials/status.php';
            ?>
        </div>

        <?php if ($documentos === []) : ?>
            <div class="text-center text-body-secondary py-5 px-3">
                <p class="fs-1 mb-2">🎉</p>
                <p class="fw-semibold text-body mb-1">Nenhum documento solicitado no momento.</p>
                <p class="small mb-0">Quando o escritório solicitar extratos ou comprovantes, eles aparecerão aqui para envio direto.</p>
            </div>
        <?php else : ?>
            <div id="lista-documentos-portal" class="d-flex flex-column gap-3">
                <?php foreach ($documentos as $doc) : ?>
                    <?php
                        $st = (string) $doc['status'];
                        $info = $statusLabels[$st] ?? ['rotulo' => $st, 'tom' => 'neutro', 'faixa' => ''];
                        $prazoStr = date('d/m/Y', strtotime((string) $doc['prazo']));
                        $atrasado = (int) ($doc['atrasada'] ?? 0) === 1;
                        $precisaEnviar = in_array($st, ['aberta', 'recusada'], true);
                        $grupoFiltro = $precisaEnviar ? 'pendentes' : ($st === 'enviada' ? 'conferencia' : 'aceitos');
                    ?>
                    <div class="card card-body card-documento-item <?= e($info['faixa']) ?><?= $atrasado ? ' documento-atrasado' : '' ?>" data-grupo-portal="<?= e($grupoFiltro) ?>">
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-center flex-wrap gap-2">
                                    <h3 class="fs-5 fw-bold mb-0">
                                        <?= e((string) $doc['titulo']) ?>
                                    </h3>
                                    <?php $seloRotulo = $info['rotulo']; $seloTom = $info['tom']; require dirname(__DIR__) . '/partials/status.php'; ?>
                                </div>
                                <div class="mt-1 small text-body-secondary d-flex align-items-center gap-2">
                                    <span>Prazo de entrega: <strong class="<?= $atrasado ? 'text-danger' : 'text-body' ?>"><?= e($prazoStr) ?></strong></span>
                                    <?php if ($atrasado) : ?>
                                        <?php $seloRotulo = 'Prazo Vencido'; $seloTom = 'perigo'; require dirname(__DIR__) . '/partials/status.php'; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <?php if (!empty($doc['descricao'])) : ?>
                            <div class="p-3 mb-3 bg-body-tertiary border rounded small">
                                <div class="fw-semibold mb-1">💡 Orientações do contador:</div>
                                <?= e((string) $doc['descricao']) ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($doc['motivo_recusa'])) : ?>
                            <div class="p-3 mb-3 rounded border border-danger-subtle bg-danger-subtle text-danger-emphasis small">
                                <div class="fw-bold d-flex align-items-center gap-1 mb-1">
                                    ⚠️ Motivo da recusa pelo contador:
                                </div>
                                <?= e((string) $doc['motivo_recusa']) ?>
                                <div class="mt-1 small">Por favor, anexe e envie um novo arquivo corrigido abaixo.</div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($doc['arquivo_path'])) : ?>
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3 py-2 px-3 bg-body-secondary rounded small">
                                <div>
                                    <span class="text-body-secondary">Arquivo anexado:</span>
                                    <strong class="ms-1"><?= e((string) $doc['arquivo_nome_original']) ?></strong>
                                    <?php if (!empty($doc['enviado_em'])) : ?>
                                        <span class="small text-body-secondary ms-2">(enviado em <?= e(date('d/m/Y H:i', strtotime((string) $doc['enviado_em']))) ?>)</span>
                                    <?php endif; ?>
                                </div>
                                <a href="<?= e(url('/portal/documentos/' . $doc['id'] . '/download')) ?>" class="btn btn-outline-secondary btn-sm">
                                    📥 Baixar arquivo
                                </a>
                            </div>
                        <?php endif; ?>

                        <?php if ($precisaEnviar) : ?>
                            <form method="post" action="<?= e(url('/portal/documentos/' . $doc['id'] . '/enviar')) ?>" enctype="multipart/form-data" class="form-envio-documento">
                                <?= csrf_campo() ?>
                                <div class="box-upload-moderno">
                                    <label for="arquivo_<?= e((string) $doc['id']) ?>" class="label-upload-customizado">
                                        <span class="icone-upload-nuvem" aria-hidden="true">📁</span>
                                        <span class="texto-upload-instrucao">
                                            <?= ($st === 'recusada') ? 'Clique para anexar arquivo corrigido' : 'Clique para escolher o arquivo' ?>
                                            <small class="texto-formatos-upload">(PDF, JPG, PNG ou XML até 5 MB)</small>
                                        </span>
                                        <span class="nome-arquivo-escolhido"></span>
                                    </label>
                                    <input type="file" id="arquivo_<?= e((string) $doc['id']) ?>" name="arquivo" class="input-arquivo-oculto" accept=".pdf,.jpg,.jpeg,.png,.xml" required>
                                    <button type="submit" class="btn btn-primary botao-submit-upload">
                                        Enviar Documento
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <p id="aviso-nenhum-doc-filtro" class="filtro-oculto text-center text-body-secondary fst-italic py-5">
                Nenhum documento com este status no momento.
            </p>
        <?php endif; ?>
    </div>

    <!-- Bloco 2: Próximas Guias e Obrigações Fiscais da Empresa -->
    <div id="secao-guias-portal" class="card card-body">
        <div class="cartao-cabecalho-flex border-bottom pb-3 mb-4">
            <div>
                <h2 class="h5 mb-1">Próximas Guias e Obrigações da Empresa</h2>
                <p class="texto-mutado mb-0">Acompanhe as guias e declarações geradas pela sua contabilidade para pagamento e conformidade.</p>
            </div>
        </div>

        <?php if ($obrigacoes === []) : ?>
            <p class="text-body-secondary text-center py-4">Nenhuma obrigação registrada para esta empresa.</p>
        <?php else : ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
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
                                <td data-rotulo="Obrigação">
                                    <strong><?= e((string) $ob['tipo_nome']) ?></strong>
                                    <?php if (!empty($ob['observacao'])) : ?>
                                        <div class="small text-body-secondary"><?= e((string) $ob['observacao']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td data-rotulo="Competência"><?= e((string) $ob['competencia']) ?></td>
                                <td data-rotulo="Vencimento">
                                    <strong><?= e(date('d/m/Y', strtotime($vencimento))) ?></strong>
                                    <?php if ($vencida) : ?>
                                        <br><?php $seloRotulo = 'Vencida'; $seloTom = 'perigo'; require dirname(__DIR__) . '/partials/status.php'; ?>
                                    <?php endif; ?>
                                </td>
                                <td data-rotulo="Valor">
                                    <?php if ($valorCentavos !== null && is_numeric($valorCentavos)) : ?>
                                        <strong><?= e('R$ ' . number_format(((int) $valorCentavos) / 100, 2, ',', '.')) ?></strong>
                                    <?php else : ?>
                                        <span class="text-body-secondary">—</span>
                                    <?php endif; ?>
                                </td>
                                <td data-rotulo="Situação">
                                    <?php
                                    if ($entregue) {
                                        $seloRotulo = 'Entregue / Paga';
                                        $seloTom = 'sucesso';
                                    } elseif ($vencida) {
                                        $seloRotulo = 'Pendente Vencida';
                                        $seloTom = 'perigo';
                                    } else {
                                        $seloRotulo = 'A Vencer';
                                        $seloTom = 'aviso';
                                    }
                                    require dirname(__DIR__) . '/partials/status.php';
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>
