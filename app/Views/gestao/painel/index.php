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
        <nav aria-label="Escopo do painel">
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

    <section class="cartao" aria-labelledby="titulo-obrigacoes">
        <h2 id="titulo-obrigacoes">Obrigações</h2>
        <ul>
            <?php foreach (['vence_hoje', 'vence_7d', 'atrasadas', 'entregues_mes'] as $chave) : ?>
                <?php if (is_array($obrigacoes[$chave] ?? null)) : ?>
                    <li><?= e((string) $obrigacoes[$chave]['rotulo']) ?>: <?= $numero($obrigacoes[$chave]) ?></li>
                <?php endif; ?>
            <?php endforeach; ?>
            <?php if (is_array($obrigacoes['entregues_no_prazo'] ?? null) && is_array($obrigacoes['entregues_mes'] ?? null)) : ?>
                <li>
                    Entregues no prazo no mês: <?= $numero($obrigacoes['entregues_no_prazo']) ?>
                    de <?= $numero($obrigacoes['entregues_mes']) ?>
                    (<span class="indicador-percentual"><?= e($pontualidade === null ? '—' : $pontualidade . '%') ?></span>)
                </li>
            <?php endif; ?>
        </ul>
    </section>

    <section class="cartao" aria-labelledby="titulo-regularidade">
        <h2 id="titulo-regularidade">Índice de Regularidade</h2>
        <ul>
            <?php foreach ($rotulosSemaforo as $cor => $rotuloCor) : ?>
                <li>
                    <?php if ($cor === 'sem_historico') : ?>
                        <?= e($rotuloCor) ?>
                    <?php else : ?>
                        <?php $nivel = $cor; require dirname(__DIR__, 2) . '/partials/semaforo.php'; ?>
                    <?php endif; ?>
                    : <span class="indicador-valor" data-semaforo="<?= e($cor) ?>"><?= e((string) (int) ($semaforo[$cor] ?? 0)) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>

        <h3>5 piores índices</h3>
        <?php if ($piores === []) : ?>
            <p>Nenhuma empresa com histórico para comparar.</p>
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
                                <td><?= e($pior['score'] === null ? '—' : (string) $pior['score']) ?></td>
                                <td><?php $nivel = (string) $pior['semaforo']; require dirname(__DIR__, 2) . '/partials/semaforo.php'; ?></td>
                                <td>
                                    <ul>
                                        <?php foreach ($pior['motivos'] as $motivo) : ?>
                                            <li><?= e((string) $motivo) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </td>
                                <td>
                                    <?php if (!empty($pior['recomendacao'])) : ?>
                                        <p><strong><?= e((string) $pior['recomendacao']) ?></strong></p>
                                        <a href="<?= e(url('/obrigacoes', ['empresa_id' => (string) $pior['empresa_id'], 'situacao' => 'atrasada'])) ?>">Ver pendências</a>
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

    <section class="cartao" aria-labelledby="titulo-carga">
        <h2 id="titulo-carga">Carga por responsável</h2>
        <p>Equipe inteira, em qualquer escopo.</p>
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

    <?php foreach ($cartoes as $cartao) : ?>
        <?php require __DIR__ . '/_cartao-padrao.php'; ?>
    <?php endforeach; ?>
</section>
