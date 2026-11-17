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
    <header class="pagina-cabecalho est-ca32a1">
        <div class="est-fbbf50">
            <div>
                <span class="est-1afdde">Painel do Cliente</span>
                <h1 class="est-057cc8">
                    <?= e((string) ($empresa['nome_fantasia'] ?: $empresa['razao_social'])) ?>
                </h1>
                <div class="est-2db23f">
                    <span class="est-0d9eab">CNPJ: <?= e(\App\Support\Cnpj::formatar((string) $empresa['cnpj'])) ?></span>
                    <span class="est-193e43">Regime: <?= e($regimeTexto) ?></span>
                    <?php if (!empty($empresa['responsavel_nome'])) : ?>
                        <span class="est-d82513">Contador Responsável: <strong><?= e((string) $empresa['responsavel_nome']) ?></strong></span>
                    <?php endif; ?>
                </div>
            </div>
            <div>
                <a href="<?= e(url('/avaliacao')) ?>" class="botao botao-secundario est-79ab17">
                    ⭐ Avaliar o Sistema
                </a>
            </div>
        </div>
    </header>

    <!-- Grid de Indicadores Rápidos do Empresário (Filtros Clicáveis) -->
    <div class="grid-kpis grid-kpis-interativos est-1bd4b9">
        <!-- Card 1: Documentos Pendentes -->
        <div class="kpi-card <?= $pendenciasCount > 0 ? 'kpi-alerta' : 'kpi-sucesso' ?>" role="button" tabindex="0" data-filtro-portal="pendentes" title="Clique para filtrar apenas documentos pendentes de envio">
            <div class="kpi-icone"><?= $pendenciasCount > 0 ? '⚠️' : '✅' ?></div>
            <div class="kpi-dados">
                <span class="kpi-rotulo">Documentos Pendentes</span>
                <span class="kpi-valor <?= $pendenciasCount > 0 ? 'valor-pendencia-alta' : 'valor-pendencia-ok' ?>">
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
                <span class="kpi-valor est-281ee0">
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
                <span class="kpi-valor est-adc654">
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
                <span class="kpi-valor est-54a69c">
                    <?= e((string) count($obrigacoes)) ?>
                </span>
                <span class="kpi-subtexto">Tributos e rotinas acompanhados</span>
            </div>
        </div>
    </div>

    <!-- Barra de Filtro Ativo -->
    <div id="barra-filtro-portal" class="barra-filtro-ativo est-f2892a">
        <div class="filtro-info-texto">
            <span class="filtro-rotulo-icone">🔍</span>
            <span id="texto-filtro-ativo">Filtrando conteúdo</span>
        </div>
        <button type="button" id="botao-limpar-filtro" class="botao-limpar-filtro">
            ✕ Mostrar tudo
        </button>
    </div>

    <!-- Bloco 1: Solicitações de Documentos (Envio Interativo) -->
    <div id="secao-documentos-portal" class="cartao est-971e16">
        <div class="cartao-cabecalho-flex est-f7dee4">
            <div>
                <h2 class="est-45133f">Documentos Solicitados pelo Escritório</h2>
                <p class="texto-mutado est-ff227d">Envie os comprovantes e extratos solicitados pela contabilidade sem precisar usar o WhatsApp.</p>
            </div>
            <span class="badge <?= $pendenciasCount > 0 ? 'badge-alerta' : 'badge-sucesso' ?> est-b018d9">
                <?= $pendenciasCount > 0 ? e((string) $pendenciasCount) . ' a enviar' : '100% em dia' ?>
            </span>
        </div>

        <?php if ($documentos === []) : ?>
            <div class="est-406d10">
                <p class="est-e24ce8">🎉</p>
                <p class="est-1869c5">Nenhum documento solicitado no momento.</p>
                <p class="est-cd5b48">Quando o escritório solicitar extratos ou comprovantes, eles aparecerão aqui para envio direto.</p>
            </div>
        <?php else : ?>
            <div class="est-5921cf" id="lista-documentos-portal">
                <?php foreach ($documentos as $doc) : ?>
                    <?php
                        $st = (string) $doc['status'];
                        $info = $statusLabels[$st] ?? ['rotulo' => $st, 'badge' => '', 'cor' => '#64748b'];
                        $prazoStr = date('d/m/Y', strtotime((string) $doc['prazo']));
                        $atrasado = (int) ($doc['atrasada'] ?? 0) === 1;
                        $precisaEnviar = in_array($st, ['aberta', 'recusada'], true);
                        $grupoFiltro = $precisaEnviar ? 'pendentes' : ($st === 'enviada' ? 'conferencia' : 'aceitos');
                    ?>
                    <div class="card-documento-item doc-status-<?= isset($statusLabels[$st]) ? e((string) $st) : 'outro' ?><?= $atrasado ? ' doc-atrasado' : '' ?>" data-grupo-portal="<?= e($grupoFiltro) ?>">
                        <div class="est-f4b07e">
                            <div class="est-253cd2">
                                <div class="est-070328">
                                    <h3 class="est-534da6">
                                        <?= e((string) $doc['titulo']) ?>
                                    </h3>
                                    <span class="badge <?= e($info['badge']) ?> est-a768c1">
                                        <?= e($info['rotulo']) ?>
                                    </span>
                                </div>
                                <div class="est-171a86">
                                    <span>Prazo de entrega: <strong class="<?= $atrasado ? 'prazo-atrasado' : 'prazo-normal' ?>"><?= e($prazoStr) ?></strong></span>
                                    <?php if ($atrasado) : ?>
                                        <span class="badge badge-perigo est-dbc8d1">Prazo Vencido</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <?php if (!empty($doc['descricao'])) : ?>
                            <div class="est-0a0b8e">
                                <div class="est-85979d">💡 Orientações do contador:</div>
                                <?= e((string) $doc['descricao']) ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($doc['motivo_recusa'])) : ?>
                            <div class="est-cdc322">
                                <div class="est-745a87">
                                    ⚠️ Motivo da recusa pelo contador:
                                </div>
                                <?= e((string) $doc['motivo_recusa']) ?>
                                <div class="est-e8b933">Por favor, anexe e envie um novo arquivo corrigido abaixo.</div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($doc['arquivo_path'])) : ?>
                            <div class="est-e6bf0d">
                                <div>
                                    <span class="est-373339">Arquivo anexado:</span>
                                    <strong class="est-5bc90a"><?= e((string) $doc['arquivo_nome_original']) ?></strong>
                                    <?php if (!empty($doc['enviado_em'])) : ?>
                                        <span class="est-f3993b">(enviado em <?= e(date('d/m/Y H:i', strtotime((string) $doc['enviado_em']))) ?>)</span>
                                    <?php endif; ?>
                                </div>
                                <a href="<?= e(url('/portal/documentos/' . $doc['id'] . '/download')) ?>" class="botao botao-pequeno botao-secundario est-f7057d">
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
                                        <span class="nome-arquivo-escolhido est-f2892a"></span>
                                    </label>
                                    <input type="file" id="arquivo_<?= e((string) $doc['id']) ?>" name="arquivo" class="input-arquivo-oculto" accept=".pdf,.jpg,.jpeg,.png,.xml" required>
                                    <button type="submit" class="botao botao-primario botao-submit-upload">
                                        Enviar Documento
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <p class="est-de0481" id="aviso-nenhum-doc-filtro">
                Nenhum documento com este status no momento.
            </p>
        <?php endif; ?>
    </div>

    <!-- Bloco 2: Próximas Guias e Obrigações Fiscais da Empresa -->
    <div id="secao-guias-portal" class="cartao">
        <div class="cartao-cabecalho-flex est-f7dee4">
            <div>
                <h2 class="est-45133f">Próximas Guias e Obrigações da Empresa</h2>
                <p class="texto-mutado est-ff227d">Acompanhe as guias e declarações geradas pela sua contabilidade para pagamento e conformidade.</p>
            </div>
        </div>

        <?php if ($obrigacoes === []) : ?>
            <p class="est-ee0b85">Nenhuma obrigação registrada para esta empresa.</p>
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
                                <td data-rotulo="Obrigação">
                                    <strong><?= e((string) $ob['tipo_nome']) ?></strong>
                                    <?php if (!empty($ob['observacao'])) : ?>
                                        <div class="est-009ac9"><?= e((string) $ob['observacao']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td data-rotulo="Competência"><?= e((string) $ob['competencia']) ?></td>
                                <td data-rotulo="Vencimento">
                                    <strong><?= e(date('d/m/Y', strtotime($vencimento))) ?></strong>
                                    <?php if ($vencida) : ?>
                                        <br><span class="badge badge-perigo est-dbc8d1">Vencida</span>
                                    <?php endif; ?>
                                </td>
                                <td data-rotulo="Valor">
                                    <?php if ($valorCentavos !== null && is_numeric($valorCentavos)) : ?>
                                        <strong><?= e('R$ ' . number_format(((int) $valorCentavos) / 100, 2, ',', '.')) ?></strong>
                                    <?php else : ?>
                                        <span class="est-4cd15e">—</span>
                                    <?php endif; ?>
                                </td>
                                <td data-rotulo="Situação">
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
