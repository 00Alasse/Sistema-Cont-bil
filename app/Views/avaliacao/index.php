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
            <p>Feedback e notas coletados no teste com usuários reais da plataforma.</p>
        </div>
        <div>
            <a href="<?= e(url('/avaliacao')) ?>" class="botao botao-secundario">+ Registrar Nova Avaliação</a>
        </div>
    </header>

    <!-- Indicadores de Satisfação -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
        <div class="cartao" style="text-align: center; padding: 1.5rem;">
            <div style="font-size: 0.875rem; color: #64748b; font-weight: 600; text-transform: uppercase;">Total de Avaliações</div>
            <div style="font-size: 2.25rem; font-weight: 700; color: #1e293b; margin-top: 0.35rem;">
                <?= e((string) $estatisticas['total']) ?>
            </div>
            <small style="color: #64748b;">respostas registradas</small>
        </div>

        <div class="cartao" style="text-align: center; padding: 1.5rem;">
            <div style="font-size: 0.875rem; color: #64748b; font-weight: 600; text-transform: uppercase;">Média Geral</div>
            <div style="font-size: 2.25rem; font-weight: 700; color: #eab308; margin-top: 0.35rem;">
                <?= e(number_format((float) $estatisticas['media_nota'], 1, ',', '.')) ?> ⭐
            </div>
            <small style="color: #64748b;">escala de 1 a 5 estrelas</small>
        </div>

        <div class="cartao" style="text-align: center; padding: 1.5rem;">
            <div style="font-size: 0.875rem; color: #64748b; font-weight: 600; text-transform: uppercase;">Facilidade de Uso</div>
            <div style="font-size: 2.25rem; font-weight: 700; color: #16a34a; margin-top: 0.35rem;">
                <?= e(number_format((float) $estatisticas['media_facilidade'], 1, ',', '.')) ?> / 5,0
            </div>
            <small style="color: #64748b;">índice de usabilidade</small>
        </div>
    </div>

    <!-- Lista de Comentários / Feedbacks -->
    <div class="cartao">
        <h2 style="font-size: 1.15rem; margin-bottom: 1rem;">Comentários e Feedbacks Recebidos</h2>

        <?php if ($itens === []) : ?>
            <p style="color: #64748b; text-align: center; padding: 2rem 0;">Nenhuma avaliação registrada até o momento.</p>
        <?php else : ?>
            <div style="display: flex; flex-direction: column; gap: 1rem;">
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
                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.25rem;">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 0.5rem;">
                            <div>
                                <strong><?= e((string) $av['usuario_nome']) ?></strong>
                                <span class="badge <?= $perfil === 'CLIENTE' ? 'badge-info' : 'badge-sucesso' ?>" style="margin-left: 0.35rem;">
                                    <?= e($perfilRotulo) ?>
                                </span>
                                <?php if (!empty($av['empresa_nome'])) : ?>
                                    <small style="color: #64748b; margin-left: 0.25rem;">(<?= e((string) $av['empresa_nome']) ?>)</small>
                                <?php endif; ?>
                            </div>
                            <div style="font-size: 0.875rem; color: #64748b;">
                                <?= e(date('d/m/Y H:i', strtotime((string) $av['criado_em']))) ?>
                            </div>
                        </div>

                        <div style="margin-bottom: 0.5rem;">
                            <span style="font-size: 1.1rem; color: #eab308;"><?= str_repeat('⭐', $nota) ?></span>
                            <span style="font-size: 0.9rem; color: #475569; margin-left: 0.5rem;">
                                (Nota: <?= e((string) $nota) ?>/5)
                            </span>
                            <?php if ($facilidade !== null) : ?>
                                <span style="font-size: 0.85rem; color: #16a34a; margin-left: 0.75rem;">
                                    • Facilidade de uso: <?= e((string) $facilidade) ?>/5
                                </span>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($av['comentario'])) : ?>
                            <blockquote style="margin: 0.5rem 0 0; background: #ffffff; border-left: 4px solid #3b82f6; padding: 0.75rem 1rem; border-radius: 4px; color: #334155; font-style: italic;">
                                "<?= e((string) $av['comentario']) ?>"
                            </blockquote>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php
            $paginaAtual = $pagina->pagina;
            $totalPaginas = max(1, $pagina->totalPaginas);
            $caminho = '/avaliacoes';
            $queryAtual = [];
            require dirname(__DIR__) . '/partials/paginacao.php';
        ?>
    </div>

    <?php require dirname(__DIR__) . '/partials/aviso-gerencial.php'; ?>
</section>
