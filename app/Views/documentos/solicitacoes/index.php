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
        <div class="acoes-formulario est-eaa887">
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
                        <th class="est-13cbe0">Ações de Conferência</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($itens === []) : ?>
                        <tr>
                            <td class="est-970260" colspan="6">
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
                                    <br><small class="est-d82513"><?= e($s['nome_fantasia']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?= e($s['titulo']) ?></strong>
                                <?php if (!empty($s['descricao'])) : ?>
                                    <br><small class="est-d82513"><?= e($s['descricao']) ?></small>
                                <?php endif; ?>
                                <?php if (!empty($s['motivo_recusa'])) : ?>
                                    <br><small class="est-6e512b"><strong>Motivo da recusa:</strong> <?= e($s['motivo_recusa']) ?></small>
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
                                        <br><small class="est-d82513">Enviado: <?= e(date('d/m H:i', strtotime((string) $s['enviado_em']))) ?></small>
                                    <?php endif; ?>
                                <?php else : ?>
                                    <span class="est-4cd15e">Aguardando envio</span>
                                <?php endif; ?>
                            </td>
                            <td class="est-8446de">
                                <?php if ($st === 'enviada') : ?>
                                    <div class="est-79e351">
                                        <form method="post" action="<?= e(url('/solicitacoes/' . $s['id'] . '/aceitar')) ?>" onsubmit="return confirm('Deseja aceitar e marcar este documento como conferido?');">
                                            <?= csrf_campo() ?>
                                            <button type="submit" class="botao botao-pequeno botao-sucesso" title="Aceitar documento">✓ Aceitar</button>
                                        </form>

                                        <details class="est-299e3a">
                                            <summary class="botao botao-pequeno botao-perigo est-8410c9">✕ Recusar</summary>
                                            <div class="est-ae4f2e">
                                                <form method="post" action="<?= e(url('/solicitacoes/' . $s['id'] . '/recusar')) ?>">
                                                    <?= csrf_campo() ?>
                                                    <label class="est-006823" for="motivo_<?= e((string) $s['id']) ?>">Motivo da recusa:</label>
                                                    <textarea class="est-511ff4" id="motivo_<?= e((string) $s['id']) ?>" name="motivo_recusa" rows="3" required placeholder="Ex.: Documento ilegível ou incompleto..."></textarea>
                                                    <div class="est-c5ae98">
                                                        <button type="submit" class="botao botao-pequeno botao-perigo">Confirmar Recusa</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </details>
                                    </div>
                                <?php elseif ($st === 'aceita') : ?>
                                    <span class="est-48d01a">Conferido</span>
                                <?php elseif ($st === 'recusada') : ?>
                                    <span class="est-b62afd">Aguardando reenvio</span>
                                <?php else : ?>
                                    <span class="est-3e4da5">Aguardando cliente</span>
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
