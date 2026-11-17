<?php

declare(strict_types=1);

$empresa = is_array($empresa ?? null) ? $empresa : [];
$documentos = is_array($documentos ?? null) ? $documentos : [];
$obrigacoes = is_array($obrigacoes ?? null) ? $obrigacoes : [];
$pendenciasCount = is_int($pendenciasCount ?? null) ? $pendenciasCount : 0;
$hoje = is_string($hoje ?? null) ? $hoje : date('Y-m-d');

$statusLabels = [
    'aberta' => ['rotulo' => 'Pendente de envio', 'badge' => 'badge-alerta'],
    'enviada' => ['rotulo' => 'Em conferência pelo escritório', 'badge' => 'badge-info'],
    'aceita' => ['rotulo' => 'Recebido e conferido', 'badge' => 'badge-sucesso'],
    'recusada' => ['rotulo' => 'Recusado (reenvio necessário)', 'badge' => 'badge-perigo'],
];
?>
<section class="pagina portal-cliente">
    <header class="pagina-cabecalho">
        <div>
            <h1>Portal da Empresa: <?= e((string) ($empresa['nome_fantasia'] ?: $empresa['razao_social'])) ?></h1>
            <p style="margin-top: 0.25rem; color: #64748b;">
                CNPJ: <?= e((string) $empresa['cnpj']) ?> • Regime: <?= e((string) $empresa['regime']) ?>
            </p>
        </div>
        <div>
            <a href="<?= e(url('/avaliacao')) ?>" class="botao botao-secundario" style="display: inline-flex; align-items: center; gap: 0.35rem;">
                ⭐ Avaliar a Plataforma
            </a>
        </div>
    </header>

    <!-- Cartão de Resumo de Pendências -->
    <div class="cartao" style="border-left: 5px solid <?= $pendenciasCount > 0 ? '#eab308' : '#16a34a' ?>; margin-bottom: 1.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div>
                <h2 style="margin: 0; font-size: 1.25rem;">
                    <?php if ($pendenciasCount === 0) : ?>
                        🎉 Parabéns! Você não tem documentos pendentes hoje.
                    <?php elseif ($pendenciasCount === 1) : ?>
                        ⚠️ Atenção: Você tem 1 documento pendente de envio.
                    <?php else : ?>
                        ⚠️ Atenção: Você tem <?= e((string) $pendenciasCount) ?> documentos pendentes de envio.
                    <?php endif; ?>
                </h2>
                <p style="margin: 0.35rem 0 0; color: #475569; font-size: 0.95rem;">
                    Envie os documentos solicitados pela contabilidade abaixo para manter sua empresa 100% em dia.
                </p>
            </div>
        </div>
    </div>

    <!-- Bloco 1: Solicitações de Documentos (Inovação Central) -->
    <div class="cartao" style="margin-bottom: 1.5rem;">
        <header style="margin-bottom: 1rem; border-bottom: 1px solid #e2e8f0; padding-bottom: 0.75rem;">
            <h2 style="font-size: 1.15rem; margin: 0;">Documentos Solicitados pelo Escritório</h2>
            <p style="margin: 0.25rem 0 0; color: #64748b; font-size: 0.9rem;">
                Selecione o arquivo no seu celular ou computador (PDF, imagens JPG/PNG ou XML até 5 MB) e envie diretamente.
            </p>
        </header>

        <?php if ($documentos === []) : ?>
            <p style="color: #64748b; text-align: center; padding: 1.5rem 0;">Nenhum documento solicitado no momento.</p>
        <?php else : ?>
            <div style="display: flex; flex-direction: column; gap: 1rem;">
                <?php foreach ($documentos as $doc) : ?>
                    <?php
                        $st = (string) $doc['status'];
                        $info = $statusLabels[$st] ?? ['rotulo' => $st, 'badge' => ''];
                        $prazoStr = date('d/m/Y', strtotime((string) $doc['prazo']));
                        $atrasado = (int) ($doc['atrasada'] ?? 0) === 1;
                        $precisaEnviar = in_array($st, ['aberta', 'recusada'], true);
                    ?>
                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.25rem;">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 0.75rem; margin-bottom: 0.75rem;">
                            <div>
                                <h3 style="margin: 0; font-size: 1.05rem; font-weight: 600; color: #0f172a;">
                                    <?= e((string) $doc['titulo']) ?>
                                </h3>
                                <div style="margin-top: 0.25rem; font-size: 0.875rem; color: #64748b;">
                                    Prazo de entrega: <strong style="<?= $atrasado ? 'color: #dc2626;' : '' ?>"><?= e($prazoStr) ?></strong>
                                    <?php if ($atrasado) : ?>
                                        <span class="badge badge-perigo" style="margin-left: 0.35rem;">Atrasado</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div>
                                <span class="badge <?= e($info['badge']) ?>"><?= e($info['rotulo']) ?></span>
                            </div>
                        </div>

                        <?php if (!empty($doc['descricao'])) : ?>
                            <div style="background: #ffffff; border: 1px solid #cbd5e1; border-radius: 6px; padding: 0.75rem; margin-bottom: 0.75rem; font-size: 0.9rem; color: #334155;">
                                <strong>Orientações do contador:</strong> <?= e((string) $doc['descricao']) ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($doc['motivo_recusa'])) : ?>
                            <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 6px; padding: 0.75rem; margin-bottom: 0.75rem; font-size: 0.9rem; color: #991b1b;">
                                <strong>Motivo da recusa pelo contador:</strong> <?= e((string) $doc['motivo_recusa']) ?>
                                <br><small>Por favor, selecione e envie um novo arquivo corrigido.</small>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($doc['arquivo_path'])) : ?>
                            <div style="margin-bottom: 0.75rem; font-size: 0.875rem;">
                                <strong>Arquivo enviado:</strong>
                                <a href="<?= e(url('/portal/documentos/' . $doc['id'] . '/download')) ?>" class="botao botao-pequeno botao-secundario" style="margin-left: 0.35rem;">
                                    📥 <?= e((string) $doc['arquivo_nome_original']) ?>
                                </a>
                                <?php if (!empty($doc['enviado_em'])) : ?>
                                    <span style="color: #64748b; margin-left: 0.5rem;">em <?= e(date('d/m/Y H:i', strtotime((string) $doc['enviado_em']))) ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($precisaEnviar) : ?>
                            <form method="post" action="<?= e(url('/portal/documentos/' . $doc['id'] . '/enviar')) ?>" enctype="multipart/form-data" style="margin-top: 0.5rem; background: #ffffff; padding: 1rem; border-radius: 6px; border: 1px dashed #94a3b8;">
                                <?= csrf_campo() ?>
                                <label for="arquivo_<?= e((string) $doc['id']) ?>" style="display: block; font-weight: 600; font-size: 0.9rem; margin-bottom: 0.35rem;">
                                    <?= ($st === 'recusada') ? 'Reenviar arquivo corrigido:' : 'Enviar arquivo:' ?>
                                </label>
                                <div style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
                                    <input type="file" id="arquivo_<?= e((string) $doc['id']) ?>" name="arquivo" accept=".pdf,.jpg,.jpeg,.png,.xml" required style="font-size: 0.9rem;">
                                    <button type="submit" class="botao botao-primario">📤 Enviar Documento</button>
                                </div>
                                <small style="display: block; margin-top: 0.35rem; color: #64748b;">Formatos permitidos: PDF, JPG, PNG e XML (Máximo 5 MB).</small>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Bloco 2: Próximas Guias e Obrigações Fiscais -->
    <div class="cartao">
        <header style="margin-bottom: 1rem; border-bottom: 1px solid #e2e8f0; padding-bottom: 0.75rem;">
            <h2 style="font-size: 1.15rem; margin: 0;">Próximas Guias e Obrigações da Empresa</h2>
            <p style="margin: 0.25rem 0 0; color: #64748b; font-size: 0.9rem;">
                Acompanhe o status e os prazos das declarações e guias geradas pela contabilidade.
            </p>
        </header>

        <?php if ($obrigacoes === []) : ?>
            <p style="color: #64748b; text-align: center; padding: 1.5rem 0;">Nenhuma obrigação registrada para esta empresa.</p>
        <?php else : ?>
            <div class="tabela-responsiva">
                <table class="tabela">
                    <thead>
                        <tr>
                            <th>Obrigação / Guia</th>
                            <th>Competência</th>
                            <th>Vencimento</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($obrigacoes as $ob) : ?>
                            <?php
                                $vencimento = (string) $ob['vencimento'];
                                $vencida = ((string) $ob['status'] !== 'ENTREGUE') && ($vencimento < $hoje);
                                $entregue = ((string) $ob['status'] === 'ENTREGUE');
                            ?>
                            <tr>
                                <td>
                                    <strong><?= e((string) $ob['tipo_nome']) ?></strong>
                                </td>
                                <td><?= e((string) $ob['competencia']) ?></td>
                                <td>
                                    <?= e(date('d/m/Y', strtotime($vencimento))) ?>
                                    <?php if ($vencida) : ?>
                                        <br><span class="badge badge-perigo">Vencida</span>
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

    <?php require dirname(__DIR__) . '/partials/aviso-gerencial.php'; ?>
</section>
