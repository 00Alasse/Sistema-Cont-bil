<?php

declare(strict_types=1);

use App\Core\Pagina;

/** @var Pagina $pagina */
$itens = $pagina->itens;
$empresas = is_array($empresas ?? null) ? $empresas : [];
$filtros = is_array($filtros ?? null) ? $filtros : [];

$statusLabels = [
    'aberta' => ['rotulo' => 'Pendente de Envio', 'tom' => 'aviso'],
    'enviada' => ['rotulo' => 'Aguardando Conferência', 'tom' => 'info'],
    'aceita' => ['rotulo' => 'Conferido e Aceito', 'tom' => 'sucesso'],
    'recusada' => ['rotulo' => 'Recusado', 'tom' => 'perigo'],
];
?>
<section class="pagina">
    <header class="pagina-cabecalho">
        <div>
            <h1>Solicitações de Documentos</h1>
            <p>Gerencie pedidos de documentos contábeis e fiscais para os seus clientes com controle de prazos e conferência.</p>
        </div>
        <a class="btn btn-primary" href="<?= e(url('/solicitacoes/nova')) ?>">+ Nova Solicitação</a>
    </header>

    <form class="card card-body formulario-filtros" method="get" action="<?= e(url('/solicitacoes')) ?>">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="q">Busca</label>
                <input class="form-control" id="q" name="q" type="search" value="<?= e((string) ($filtros['busca'] ?? '')) ?>" placeholder="Título ou nome da empresa">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="empresa_id">Empresa</label>
                <select class="form-select" id="empresa_id" name="empresa_id">
                    <option value="">Todas as empresas</option>
                    <?php foreach ($empresas as $emp) : ?>
                        <option value="<?= e((string) $emp['id']) ?>" <?= ((string) ($filtros['empresa_id'] ?? '') === (string) $emp['id']) ? 'selected' : '' ?>>
                            <?= e($emp['razao_social']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="status">Situação</label>
                <select class="form-select" id="status" name="status">
                    <option value="">Todas</option>
                    <option value="aberta" <?= (($filtros['status'] ?? '') === 'aberta') ? 'selected' : '' ?>>Pendente de Envio</option>
                    <option value="enviada" <?= (($filtros['status'] ?? '') === 'enviada') ? 'selected' : '' ?>>Aguardando Conferência</option>
                    <option value="aceita" <?= (($filtros['status'] ?? '') === 'aceita') ? 'selected' : '' ?>>Conferido e Aceito</option>
                    <option value="recusada" <?= (($filtros['status'] ?? '') === 'recusada') ? 'selected' : '' ?>>Recusado</option>
                </select>
            </div>
        </div>
        <div class="d-flex gap-2 mt-3">
            <button class="btn btn-primary" type="submit">Filtrar</button>
            <a class="btn btn-outline-secondary" href="<?= e(url('/solicitacoes')) ?>">Limpar</a>
        </div>
    </form>

    <div class="card cartao-tabela">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th scope="col">Empresa</th>
                        <th scope="col">Documento Solicitado</th>
                        <th scope="col">Prazo</th>
                        <th scope="col">Status</th>
                        <th scope="col">Arquivo</th>
                        <th scope="col" class="text-end">Ações de Conferência</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($itens === []) : ?>
                        <tr>
                            <td colspan="6" class="text-center text-body-secondary py-4">
                                Nenhuma solicitação de documento encontrada.
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($itens as $s) : ?>
                        <?php
                            $st = (string) $s['status'];
                            $infoStatus = $statusLabels[$st] ?? ['rotulo' => $st, 'tom' => 'neutro'];
                            $atrasada = (int) ($s['atrasada'] ?? 0) === 1;
                        ?>
                        <tr>
                            <td>
                                <strong><?= e($s['empresa_nome']) ?></strong>
                                <?php if (!empty($s['nome_fantasia'])) : ?>
                                    <br><small class="text-body-secondary"><?= e($s['nome_fantasia']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?= e($s['titulo']) ?></strong>
                                <?php if (!empty($s['descricao'])) : ?>
                                    <br><small class="text-body-secondary"><?= e($s['descricao']) ?></small>
                                <?php endif; ?>
                                <?php if (!empty($s['motivo_recusa'])) : ?>
                                    <br><small class="text-danger"><strong>Motivo da recusa:</strong> <?= e($s['motivo_recusa']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= e(date('d/m/Y', strtotime((string) $s['prazo']))) ?>
                                <?php if ($atrasada) : ?>
                                    <br><?php $seloRotulo = 'Atrasado'; $seloTom = 'perigo'; require dirname(__DIR__, 2) . '/partials/status.php'; ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php $seloRotulo = $infoStatus['rotulo']; $seloTom = $infoStatus['tom']; require dirname(__DIR__, 2) . '/partials/status.php'; ?>
                            </td>
                            <td>
                                <?php if (!empty($s['arquivo_path'])) : ?>
                                    <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('/solicitacoes/' . $s['id'] . '/download')) ?>" title="Baixar <?= e((string) $s['arquivo_nome_original']) ?>">
                                        📥 <?= e((string) $s['arquivo_nome_original']) ?>
                                    </a>
                                    <?php if (!empty($s['enviado_em'])) : ?>
                                        <br><small class="text-body-secondary">Enviado: <?= e(date('d/m H:i', strtotime((string) $s['enviado_em']))) ?></small>
                                    <?php endif; ?>
                                <?php else : ?>
                                    <span class="text-body-secondary">Aguardando envio</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <?php if ($st === 'enviada') : ?>
                                    <div class="d-flex flex-wrap justify-content-end align-items-start gap-1">
                                        <form method="post" action="<?= e(url('/solicitacoes/' . $s['id'] . '/aceitar')) ?>" data-confirmar="Deseja aceitar e marcar este documento como conferido?">
                                            <?= csrf_campo() ?>
                                            <button type="submit" class="btn btn-success btn-sm" title="Aceitar documento">✓ Aceitar</button>
                                        </form>

                                        <?php /* Com JS: este botão abre o modal. Sem JS (html sem a classe "js"): o botão some e o <details> abaixo faz o papel dele. */ ?>
                                        <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#modal-recusa-<?= e((string) $s['id']) ?>">✕ Recusar</button>

                                        <details class="recusa-sem-js text-start">
                                            <summary class="btn btn-danger btn-sm">✕ Recusar</summary>
                                            <form class="mt-2" method="post" action="<?= e(url('/solicitacoes/' . $s['id'] . '/recusar')) ?>">
                                                <?= csrf_campo() ?>
                                                <label class="form-label fw-semibold small" for="motivo-simples-<?= e((string) $s['id']) ?>">Motivo da recusa:</label>
                                                <textarea class="form-control mb-2" id="motivo-simples-<?= e((string) $s['id']) ?>" name="motivo_recusa" rows="3" cols="28" required placeholder="Ex.: Documento ilegível ou incompleto..."></textarea>
                                                <button type="submit" class="btn btn-danger btn-sm">Confirmar Recusa</button>
                                            </form>
                                        </details>
                                    </div>
                                <?php elseif ($st === 'aceita') : ?>
                                    <span class="text-success fw-semibold small">Conferido</span>
                                <?php elseif ($st === 'recusada') : ?>
                                    <span class="text-danger small">Aguardando reenvio</span>
                                <?php else : ?>
                                    <span class="text-body-secondary small">Aguardando cliente</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php
            // O partial lê $pagina como número; aqui $pagina é o objeto Pagina, então ele é trocado pelo número.
            $totalPaginas = max(1, $pagina->totalPaginas);
            $pagina = $pagina->pagina;
            $caminho = '/solicitacoes';
            $queryAtual = $filtros;
            require dirname(__DIR__, 2) . '/partials/paginacao.php';
        ?>
    </div>

    <?php /* Os modais ficam fora da tabela (.table-responsive tem overflow) e do cartão, como o Bootstrap recomenda. */ ?>
    <?php foreach ($itens as $s) : ?>
        <?php if ((string) $s['status'] === 'enviada') : ?>
            <?php $idModal = (string) $s['id']; ?>
            <div class="modal fade" id="modal-recusa-<?= e($idModal) ?>" tabindex="-1" aria-labelledby="titulo-modal-recusa-<?= e($idModal) ?>" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <form class="modal-content" method="post" action="<?= e(url('/solicitacoes/' . $s['id'] . '/recusar')) ?>">
                        <?= csrf_campo() ?>
                        <div class="modal-header">
                            <h2 class="modal-title fs-5" id="titulo-modal-recusa-<?= e($idModal) ?>">Recusar documento</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                        </div>
                        <div class="modal-body">
                            <p class="small text-body-secondary mb-3">
                                <strong><?= e($s['titulo']) ?></strong> — <?= e($s['empresa_nome']) ?>
                            </p>
                            <label class="form-label fw-semibold" for="motivo_<?= e($idModal) ?>">Motivo da recusa:</label>
                            <textarea class="form-control" id="motivo_<?= e($idModal) ?>" name="motivo_recusa" rows="4" required placeholder="Ex.: Documento ilegível ou incompleto..."></textarea>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-danger">Confirmar Recusa</button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>

    <?php require dirname(__DIR__, 2) . '/partials/aviso-gerencial.php'; ?>
</section>
