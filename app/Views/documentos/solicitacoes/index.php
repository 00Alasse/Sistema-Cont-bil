<?php

declare(strict_types=1);

use App\Core\Pagina;

/** @var Pagina $pagina */
$itens = $pagina->itens;
$empresas = is_array($empresas ?? null) ? $empresas : [];
$filtros = is_array($filtros ?? null) ? $filtros : [];

$statusLabels = [
    'aberta' => ['rotulo' => 'Pendente de Envio', 'classe' => 'badge-alerta'],
    'enviada' => ['rotulo' => 'Aguardando Conferência', 'classe' => 'badge-info'],
    'aceita' => ['rotulo' => 'Conferido e Aceito', 'classe' => 'badge-sucesso'],
    'recusada' => ['rotulo' => 'Recusado', 'classe' => 'badge-perigo'],
];
?>
<section class="pagina">
    <header class="pagina-cabecalho">
        <div>
            <h1>Solicitações de Documentos</h1>
            <p>Gerencie pedidos de documentos contábeis e fiscais para os seus clientes com controle de prazos e conferência.</p>
        </div>
        <a class="botao botao-primario" href="<?= e(url('/solicitacoes/nova')) ?>">+ Nova Solicitação</a>
    </header>

    <form class="cartao formulario-filtros" method="get" action="<?= e(url('/solicitacoes')) ?>">
        <div class="grade-formulario">
            <div class="campo campo-largo">
                <label for="q">Busca</label>
                <input id="q" name="q" type="search" value="<?= e((string) ($filtros['busca'] ?? '')) ?>" placeholder="Título ou nome da empresa">
            </div>
            <div class="campo">
                <label for="empresa_id">Empresa</label>
                <select id="empresa_id" name="empresa_id">
                    <option value="">Todas as empresas</option>
                    <?php foreach ($empresas as $emp) : ?>
                        <option value="<?= e((string) $emp['id']) ?>" <?= ((string) ($filtros['empresa_id'] ?? '') === (string) $emp['id']) ? 'selected' : '' ?>>
                            <?= e($emp['razao_social']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="campo">
                <label for="status">Situação</label>
                <select id="status" name="status">
                    <option value="">Todas</option>
                    <option value="aberta" <?= (($filtros['status'] ?? '') === 'aberta') ? 'selected' : '' ?>>Pendente de Envio</option>
                    <option value="enviada" <?= (($filtros['status'] ?? '') === 'enviada') ? 'selected' : '' ?>>Aguardando Conferência</option>
                    <option value="aceita" <?= (($filtros['status'] ?? '') === 'aceita') ? 'selected' : '' ?>>Conferido e Aceito</option>
                    <option value="recusada" <?= (($filtros['status'] ?? '') === 'recusada') ? 'selected' : '' ?>>Recusado</option>
                </select>
            </div>
        </div>
        <div class="acoes-formulario" style="margin-top: 1rem; display: flex; gap: 0.5rem;">
            <button class="botao botao-primario" type="submit">Filtrar</button>
            <a class="botao botao-secundario" href="<?= e(url('/solicitacoes')) ?>">Limpar</a>
        </div>
    </form>

    <div class="cartao">
        <div class="tabela-responsiva">
            <table class="tabela">
                <thead>
                    <tr>
                        <th>Empresa</th>
                        <th>Documento Solicitado</th>
                        <th>Prazo</th>
                        <th>Status</th>
                        <th>Arquivo</th>
                        <th style="text-align: right;">Ações de Conferência</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($itens === []) : ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 2rem; color: #64748b;">
                                Nenhuma solicitação de documento encontrada.
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($itens as $s) : ?>
                        <?php
                            $st = (string) $s['status'];
                            $infoStatus = $statusLabels[$st] ?? ['rotulo' => $st, 'classe' => ''];
                            $atrasada = (int) ($s['atrasada'] ?? 0) === 1;
                        ?>
                        <tr>
                            <td>
                                <strong><?= e($s['empresa_nome']) ?></strong>
                                <?php if (!empty($s['nome_fantasia'])) : ?>
                                    <br><small style="color: #64748b;"><?= e($s['nome_fantasia']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?= e($s['titulo']) ?></strong>
                                <?php if (!empty($s['descricao'])) : ?>
                                    <br><small style="color: #64748b;"><?= e($s['descricao']) ?></small>
                                <?php endif; ?>
                                <?php if (!empty($s['motivo_recusa'])) : ?>
                                    <br><small style="color: #dc2626;"><strong>Motivo da recusa:</strong> <?= e($s['motivo_recusa']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= e(date('d/m/Y', strtotime((string) $s['prazo']))) ?>
                                <?php if ($atrasada) : ?>
                                    <br><span class="badge badge-perigo">Atrasado</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?= e($infoStatus['classe']) ?>"><?= e($infoStatus['rotulo']) ?></span>
                            </td>
                            <td>
                                <?php if (!empty($s['arquivo_path'])) : ?>
                                    <a class="botao botao-pequeno botao-secundario" href="<?= e(url('/solicitacoes/' . $s['id'] . '/download')) ?>" title="Baixar <?= e((string) $s['arquivo_nome_original']) ?>">
                                        📥 <?= e((string) $s['arquivo_nome_original']) ?>
                                    </a>
                                    <?php if (!empty($s['enviado_em'])) : ?>
                                        <br><small style="color: #64748b;">Enviado: <?= e(date('d/m H:i', strtotime((string) $s['enviado_em']))) ?></small>
                                    <?php endif; ?>
                                <?php else : ?>
                                    <span style="color: #94a3b8;">Aguardando envio</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right; white-space: nowrap;">
                                <?php if ($st === 'enviada') : ?>
                                    <div style="display: inline-flex; gap: 0.35rem; justify-content: flex-end;">
                                        <form method="post" action="<?= e(url('/solicitacoes/' . $s['id'] . '/aceitar')) ?>" onsubmit="return confirm('Deseja aceitar e marcar este documento como conferido?');">
                                            <?= csrf_campo() ?>
                                            <button type="submit" class="botao botao-pequeno botao-sucesso" title="Aceitar documento">✓ Aceitar</button>
                                        </form>

                                        <details style="display: inline-block; position: relative; text-align: left;">
                                            <summary class="botao botao-pequeno botao-perigo" style="cursor: pointer;">✕ Recusar</summary>
                                            <div style="position: absolute; right: 0; background: #fff; padding: 1rem; border: 1px solid #cbd5e1; border-radius: 6px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); z-index: 100; width: 280px;">
                                                <form method="post" action="<?= e(url('/solicitacoes/' . $s['id'] . '/recusar')) ?>">
                                                    <?= csrf_campo() ?>
                                                    <label for="motivo_<?= e((string) $s['id']) ?>" style="display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.25rem;">Motivo da recusa:</label>
                                                    <textarea id="motivo_<?= e((string) $s['id']) ?>" name="motivo_recusa" rows="3" style="width: 100%; box-sizing: border-box; font-size: 0.85rem; padding: 0.4rem; border: 1px solid #cbd5e1; border-radius: 4px;" required placeholder="Ex.: Documento ilegível ou incompleto..."></textarea>
                                                    <div style="margin-top: 0.5rem; text-align: right;">
                                                        <button type="submit" class="botao botao-pequeno botao-perigo">Confirmar Recusa</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </details>
                                    </div>
                                <?php elseif ($st === 'aceita') : ?>
                                    <span style="color: #16a34a; font-weight: 600; font-size: 0.875rem;">Conferido</span>
                                <?php elseif ($st === 'recusada') : ?>
                                    <span style="color: #dc2626; font-size: 0.875rem;">Aguardando reenvio</span>
                                <?php else : ?>
                                    <span style="color: #94a3b8; font-size: 0.875rem;">Aguardando cliente</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php
            $paginaAtual = $pagina->pagina;
            $totalPaginas = max(1, $pagina->totalPaginas);
            $caminho = '/solicitacoes';
            $queryAtual = $filtros;
            require dirname(__DIR__, 2) . '/partials/paginacao.php';
        ?>
    </div>

    <?php require dirname(__DIR__, 2) . '/partials/aviso-gerencial.php'; ?>
</section>
