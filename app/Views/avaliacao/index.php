<?php

declare(strict_types=1);

use App\Core\Pagina;

/** @var Pagina $pagina */
$itens = $pagina->itens;
$estatisticas = is_array($estatisticas ?? null) ? $estatisticas : ['total' => 0, 'media_nota' => 0.0, 'media_facilidade' => 0.0];
?>
<section class="pagina">
    <header class="pagina-cabecalho">
        <div>
            <h1>Avaliações dos Usuários</h1>
            <p>Espaço destinado à avaliação da experiência de uso e à coleta de feedback dos usuários da plataforma.</p>
        </div>
        <div>
            <a href="<?= e(url('/avaliacao')) ?>" class="btn btn-outline-secondary">+ Registrar Nova Avaliação</a>
        </div>
    </header>

    <!-- Indicadores de Satisfação -->
    <div class="grid-kpis">
        <div class="kpi-card">
            <div class="kpi-icone">📋</div>
            <div class="kpi-dados">
                <span class="kpi-rotulo">Total de Avaliações</span>
                <span class="kpi-valor"><?= e((string) $estatisticas['total']) ?></span>
                <span class="kpi-subtexto">respostas registradas</span>
            </div>
        </div>

        <div class="kpi-card kpi-alerta">
            <div class="kpi-icone">⭐</div>
            <div class="kpi-dados">
                <span class="kpi-rotulo">Média Geral</span>
                <span class="kpi-valor text-warning-emphasis"><?= e(number_format((float) $estatisticas['media_nota'], 1, ',', '.')) ?></span>
                <span class="kpi-subtexto">escala de 1 a 5 estrelas</span>
            </div>
        </div>

        <div class="kpi-card kpi-sucesso">
            <div class="kpi-icone">👍</div>
            <div class="kpi-dados">
                <span class="kpi-rotulo">Facilidade de Uso</span>
                <span class="kpi-valor text-success-emphasis"><?= e(number_format((float) $estatisticas['media_facilidade'], 1, ',', '.')) ?></span>
                <span class="kpi-subtexto">índice de usabilidade (até 5,0)</span>
            </div>
        </div>
    </div>

    <!-- Lista de Comentários / Feedbacks -->
    <div class="card card-body">
        <h2 class="h5 mb-3">Comentários e Feedbacks Recebidos</h2>

        <?php if ($itens === []) : ?>
            <p class="text-body-secondary text-center py-4">Nenhuma avaliação registrada até o momento.</p>
        <?php else : ?>
            <div class="d-flex flex-column gap-3">
                <?php foreach ($itens as $av) : ?>
                    <?php
                        $nota = (int) $av['nota'];
                        $facilidade = !empty($av['facilidade']) ? (int) $av['facilidade'] : null;
                        $perfil = (string) $av['usuario_perfil'];
                        $perfilRotulo = match ($perfil) {
                            'CLIENTE' => 'Empresário / Cliente',
                            'ADMIN' => 'Contador Responsável',
                            default => 'Colaborador do Escritório'
                        };
                    ?>
                    <div class="p-3 border rounded bg-body-tertiary">
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                            <div>
                                <strong><?= e((string) $av['usuario_nome']) ?></strong>
                                <span class="ms-1">
                                    <?php $seloRotulo = $perfilRotulo; $seloTom = $perfil === 'CLIENTE' ? 'info' : 'sucesso'; require dirname(__DIR__) . '/partials/status.php'; ?>
                                </span>
                                <?php if (!empty($av['empresa_nome'])) : ?>
                                    <small class="text-body-secondary ms-1">(<?= e((string) $av['empresa_nome']) ?>)</small>
                                <?php endif; ?>
                            </div>
                            <div class="small text-body-secondary">
                                <?= e(date('d/m/Y H:i', strtotime((string) $av['criado_em']))) ?>
                            </div>
                        </div>

                        <div class="mb-2">
                            <span class="fs-5"><?= str_repeat('⭐', $nota) ?></span>
                            <span class="small text-body-secondary ms-2">
                                (Nota: <?= e((string) $nota) ?>/5)
                            </span>
                            <?php if ($facilidade !== null) : ?>
                                <span class="small text-success ms-3">
                                    • Facilidade de uso: <?= e((string) $facilidade) ?>/5
                                </span>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($av['comentario'])) : ?>
                            <blockquote class="mb-0 mt-2 bg-body border-start border-4 border-primary rounded py-2 px-3 fst-italic text-body-secondary">
                                "<?= e((string) $av['comentario']) ?>"
                            </blockquote>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php
            // O partial lê $pagina como número; aqui $pagina é o objeto Pagina, então ele é trocado pelo número.
            $totalPaginas = max(1, $pagina->totalPaginas);
            $pagina = $pagina->pagina;
            $caminho = '/avaliacoes';
            $queryAtual = [];
            require dirname(__DIR__) . '/partials/paginacao.php';
        ?>
    </div>

    <?php require dirname(__DIR__) . '/partials/aviso-gerencial.php'; ?>
</section>
