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
    <div class="est-726850">
        <div class="cartao est-39153e">
            <div class="est-90823f">Total de Avaliações</div>
            <div class="est-cba55c">
                <?= e((string) $estatisticas['total']) ?>
            </div>
            <small class="est-d82513">respostas registradas</small>
        </div>

        <div class="cartao est-39153e">
            <div class="est-90823f">Média Geral</div>
            <div class="est-5959d8">
                <?= e(number_format((float) $estatisticas['media_nota'], 1, ',', '.')) ?> ⭐
            </div>
            <small class="est-d82513">escala de 1 a 5 estrelas</small>
        </div>

        <div class="cartao est-39153e">
            <div class="est-90823f">Facilidade de Uso</div>
            <div class="est-fe7d19">
                <?= e(number_format((float) $estatisticas['media_facilidade'], 1, ',', '.')) ?> / 5,0
            </div>
            <small class="est-d82513">índice de usabilidade</small>
        </div>
    </div>

    <!-- Lista de Comentários / Feedbacks -->
    <div class="cartao">
        <h2 class="est-6be223">Comentários e Feedbacks Recebidos</h2>

        <?php if ($itens === []) : ?>
            <p class="est-ee0b85">Nenhuma avaliação registrada até o momento.</p>
        <?php else : ?>
            <div class="est-25d6a4">
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
                    <div class="est-5ad78a">
                        <div class="est-fd15cb">
                            <div>
                                <strong><?= e((string) $av['usuario_nome']) ?></strong>
                                <span class="badge <?= $perfil === 'CLIENTE' ? 'badge-info' : 'badge-sucesso' ?> est-123a5d">
                                    <?= e($perfilRotulo) ?>
                                </span>
                                <?php if (!empty($av['empresa_nome'])) : ?>
                                    <small class="est-a2b502">(<?= e((string) $av['empresa_nome']) ?>)</small>
                                <?php endif; ?>
                            </div>
                            <div class="est-4cc262">
                                <?= e(date('d/m/Y H:i', strtotime((string) $av['criado_em']))) ?>
                            </div>
                        </div>

                        <div class="est-1c09f0">
                            <span class="est-39c3c8"><?= str_repeat('⭐', $nota) ?></span>
                            <span class="est-e37834">
                                (Nota: <?= e((string) $nota) ?>/5)
                            </span>
                            <?php if ($facilidade !== null) : ?>
                                <span class="est-dc52d4">
                                    • Facilidade de uso: <?= e((string) $facilidade) ?>/5
                                </span>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($av['comentario'])) : ?>
                            <blockquote class="est-e55af4">
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
