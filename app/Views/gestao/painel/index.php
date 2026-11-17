<?php

declare(strict_types=1);

$painel = is_array($painel ?? null) ? $painel : [];
$cartoes = is_array($cartoes ?? null) ? $cartoes : [];
$escopo = is_string($painel['escopo'] ?? null) ? $painel['escopo'] : 'todos';
$obrigacoes = is_array($painel['obrigacoes'] ?? null) ? $painel['obrigacoes'] : [];
$semaforo = is_array($painel['semaforo'] ?? null) ? $painel['semaforo'] : [];
$piores = is_array($painel['piores'] ?? null) ? $painel['piores'] : [];
$carga = is_array($painel['carga'] ?? null) ? $painel['carga'] : [];
$pontualidade = is_int($painel['pontualidade'] ?? null) ? $painel['pontualidade'] : null;
$escopos = ['meus' => 'Minhas obrigações e empresas', 'todos' => 'Todo o escritório'];
$rotulosSemaforo = ['verde' => 'Em dia', 'amarelo' => 'Atenção', 'vermelho' => 'Pendência', 'sem_historico' => 'Sem histórico'];

/** Número com link para a listagem já filtrada (ou só o número, quando não há filtro equivalente). */
$numero = static function (array $item): string {
    $valor = (string) (int) ($item['valor'] ?? 0);
    $query = $item['query'] ?? null;

    return is_array($query)
        ? '<a class="indicador-valor" href="' . e(url((string) ($item['caminho'] ?? '/obrigacoes'), $query)) . '">' . e($valor) . '</a>'
        : '<span class="indicador-valor">' . e($valor) . '</span>';
};
?>
<section class="pagina">
    <header class="pagina-cabecalho">
        <div>
            <h1>Painel do escritório</h1>
            <p>Mostrando: <strong><?= e($escopos[$escopo] ?? $escopo) ?></strong></p>
        </div>
        <nav aria-label="Escopo do painel" style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
            <?php foreach ($escopos as $codigo => $rotulo) : ?>
                <?php if ($codigo === $escopo) : ?>
                    <span class="botao" aria-current="page"><?= e($rotulo) ?></span>
                <?php else : ?>
                    <a class="botao botao-secundario" href="<?= e(url('/painel', ['escopo' => $codigo])) ?>"><?= e($rotulo) ?></a>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>
    </header>

    <?php
    $tituloAviso = '';
    $urlAcao = '';
    $rotuloAcao = '';
    $mensagem = 'Indicadores gerenciais, calculados só com os registros do escritório. Não atestam regularidade fiscal.';
    require dirname(__DIR__, 2) . '/partials/aviso-gerencial.php';
    ?>

    <!-- Métricas Rápidas de Obrigações -->
    <section class="cartao" aria-labelledby="titulo-obrigacoes">
        <div class="cartao-cabecalho-flex">
            <div>
                <h2 id="titulo-obrigacoes" style="margin: 0 0 0.25rem;">Obrigações</h2>
                <p class="texto-mutado" style="margin: 0;">Monitoramento de prazos legais e cumprimento das rotinas fiscais.</p>
            </div>
            <a href="<?= e(url('/obrigacoes/nova')) ?>" class="botao botao-pequeno botao-secundario">+ Nova Obrigação</a>
        </div>

        <div class="grid-kpis">
            <!-- Atrasadas -->
            <div class="kpi-card kpi-perigo">
                <div class="kpi-icone">⚠️</div>
                <div class="kpi-dados">
                    <span class="kpi-rotulo">Atrasadas</span>
                    <span class="kpi-valor"><?= is_array($obrigacoes['atrasadas'] ?? null) ? $numero($obrigacoes['atrasadas']) : '0' ?></span>
                    <span class="kpi-subtexto">Requerem regularização imediata</span>
                </div>
            </div>

            <!-- Vence Hoje -->
            <div class="kpi-card kpi-alerta">
                <div class="kpi-icone">🔔</div>
                <div class="kpi-dados">
                    <span class="kpi-rotulo">Vencem hoje</span>
                    <span class="kpi-valor"><?= is_array($obrigacoes['vence_hoje'] ?? null) ? $numero($obrigacoes['vence_hoje']) : '0' ?></span>
                    <span class="kpi-subtexto">Prioridade máxima no dia</span>
                </div>
            </div>

            <!-- Vencem em 7 dias -->
            <div class="kpi-card kpi-info">
                <div class="kpi-icone">📅</div>
                <div class="kpi-dados">
                    <span class="kpi-rotulo">Vencem em 7 dias</span>
                    <span class="kpi-valor"><?= is_array($obrigacoes['vence_7d'] ?? null) ? $numero($obrigacoes['vence_7d']) : '0' ?></span>
                    <span class="kpi-subtexto">Próximos vencimentos</span>
                </div>
            </div>

            <!-- Entregues no Mês -->
            <div class="kpi-card kpi-sucesso">
                <div class="kpi-icone">✅</div>
                <div class="kpi-dados">
                    <span class="kpi-rotulo">Entregues no mês</span>
                    <span class="kpi-valor"><?= is_array($obrigacoes['entregues_mes'] ?? null) ? $numero($obrigacoes['entregues_mes']) : '0' ?></span>
                    <?php if (is_array($obrigacoes['entregues_no_prazo'] ?? null) && is_array($obrigacoes['entregues_mes'] ?? null)) : ?>
                        <span class="kpi-subtexto">
                            <?= $numero($obrigacoes['entregues_no_prazo']) ?> no prazo
                            (<strong class="indicador-percentual"><?= e($pontualidade === null ? '—' : $pontualidade . '%') ?></strong>)
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Cartões Modulares (ex: Documentos dos Clientes) -->
    <?php foreach ($cartoes as $cartao) : ?>
        <?php require __DIR__ . '/_cartao-padrao.php'; ?>
    <?php endforeach; ?>

    <!-- Índice de Regularidade Fiscal -->
    <section class="cartao" aria-labelledby="titulo-regularidade">
        <h2 id="titulo-regularidade">Índice de Regularidade</h2>
        <p class="texto-mutado">Pontuação preventiva (0–100) baseada na adimplência fiscal das empresas.</p>

        <div class="grid-semaforo">
            <?php foreach ($rotulosSemaforo as $cor => $rotuloCor) : ?>
                <div class="card-semaforo card-semaforo-<?= e($cor) ?>">
                    <div class="semaforo-cabecalho">
                        <?php if ($cor === 'sem_historico') : ?>
                            <span style="display: inline-block; width: 0.75rem; height: 0.75rem; border-radius: 50%; background: #94a3b8;"></span>
                            <?= e($rotuloCor) ?>
                        <?php else : ?>
                            <?php $nivel = $cor; require dirname(__DIR__, 2) . '/partials/semaforo.php'; ?>
                            <?= e($rotuloCor) ?>
                        <?php endif; ?>
                    </div>
                    <div class="semaforo-contagem">
                        <span class="indicador-valor" data-semaforo="<?= e($cor) ?>"><?= e((string) (int) ($semaforo[$cor] ?? 0)) ?></span>
                        <span class="semaforo-legenda">empresas</span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <h3 style="margin-top: 1.75rem; margin-bottom: 0.75rem;">5 piores índices</h3>
        <?php if ($piores === []) : ?>
            <p class="texto-mutado">Nenhuma empresa com histórico para comparar.</p>
        <?php else : ?>
            <div class="tabela-responsiva">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">Empresa</th>
                            <th scope="col">Índice</th>
                            <th scope="col">Situação</th>
                            <th scope="col">Motivos</th>
                            <th scope="col">Recomendação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($piores as $pior) : ?>
                            <tr>
                                <th scope="row"><a href="<?= e(url((string) $pior['caminho'])) ?>"><?= e((string) $pior['nome']) ?></a></th>
                                <td><strong><?= e($pior['score'] === null ? '—' : (string) $pior['score']) ?></strong></td>
                                <td><?php $nivel = (string) $pior['semaforo']; require dirname(__DIR__, 2) . '/partials/semaforo.php'; ?></td>
                                <td>
                                    <ul style="margin: 0; padding-left: 1.25rem;">
                                        <?php foreach ($pior['motivos'] as $motivo) : ?>
                                            <li><?= e((string) $motivo) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </td>
                                <td>
                                    <?php if (!empty($pior['recomendacao'])) : ?>
                                        <p style="margin: 0 0 0.25rem;"><strong><?= e((string) $pior['recomendacao']) ?></strong></p>
                                        <a href="<?= e(url('/obrigacoes', ['empresa_id' => (string) $pior['empresa_id'], 'situacao' => 'atrasada'])) ?>" class="botao botao-pequeno botao-secundario">Ver pendências</a>
                                    <?php else : ?>
                                        —
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <!-- Carga por Responsável -->
    <section class="cartao" aria-labelledby="titulo-carga">
        <h2 id="titulo-carga">Carga por responsável</h2>
        <p class="texto-mutado">Distribuição de demandas na equipe (todo o escritório).</p>
        <div class="tabela-responsiva">
            <table>
                <thead>
                    <tr>
                        <th scope="col">Responsável</th>
                        <th scope="col">Atrasadas</th>
                        <th scope="col">Vencem em até 7 dias</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($carga as $linha) : ?>
                        <tr>
                            <th scope="row"><?= e((string) $linha['nome']) ?></th>
                            <td><?= $numero($linha['atrasadas'] + ['caminho' => '/obrigacoes']) ?></td>
                            <td><?= $numero($linha['vence_7d'] + ['caminho' => '/obrigacoes']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($carga === []) : ?>
                        <tr><td colspan="3">Nenhum responsável cadastrado.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</section>
