<?php

declare(strict_types=1);

use App\Core\Pagina;

$resultado = $paginaObrigacoes ?? null;
$resultado = $resultado instanceof Pagina ? $resultado : new Pagina([], 0, 1, 20);
$filtros = is_array($filtros ?? null) ? $filtros : [];
$filtrosAtivos = is_array($filtrosAtivos ?? null) ? $filtrosAtivos : [];
$queryAtual = is_array($queryAtual ?? null) ? $queryAtual : [];
$empresas = is_array($empresas ?? null) ? $empresas : [];
$tipos = is_array($tipos ?? null) ? $tipos : [];
$responsaveis = is_array($responsaveis ?? null) ? $responsaveis : [];
$rotulosStatus = is_array($rotulosStatus ?? null) ? $rotulosStatus : [];
$rotulosSituacao = is_array($rotulosSituacao ?? null) ? $rotulosSituacao : [];
$valorFiltro = static fn (string $campo): string => is_scalar($filtros[$campo] ?? null)
    ? (string) $filtros[$campo]
    : '';
$selecionado = static fn (string $campo, string $valor): string => $valorFiltro($campo) === $valor ? ' selected' : '';
// "prioridade" é o padrão: primeiro o que exige atenção (atraso crítico, atrasadas, vence hoje, vence em até 7 dias).
$ordens = [
    'prioridade' => 'Prioridade (o que exige atenção primeiro)',
    'vencimento' => 'Vencimento (mais próximo)',
    '-vencimento' => 'Vencimento (mais distante)',
    'competencia' => 'Competência (mais antiga)',
    '-competencia' => 'Competência (mais recente)',
    'empresa' => 'Empresa (A–Z)',
    '-empresa' => 'Empresa (Z–A)',
];
?>
<section class="pagina">
    <header class="pagina-cabecalho">
        <div>
            <h1>Obrigações</h1>
            <p>Obrigações das empresas atendidas, com vencimento e responsável.</p>
        </div>
        <a class="btn btn-primary" href="<?= e(url('/obrigacoes/nova')) ?>">
            <?php $icone = 'adicionar'; require dirname(__DIR__, 2) . '/partials/icone.php'; ?>
            Cadastrar obrigação
        </a>
    </header>

    <form class="card card-body formulario-filtros" method="get" action="<?= e(url('/obrigacoes')) ?>">
        <div class="row g-3">
            <div class="col-12">
                <label class="form-label" for="q">Busca</label>
                <input class="form-control" id="q" name="q" type="search" value="<?= e($valorFiltro('q')) ?>" placeholder="Empresa ou tipo de obrigação">
            </div>
            <div class="col-md-4 col-xl-3">
                <label class="form-label" for="empresa_id">Empresa</label>
                <select class="form-select" id="empresa_id" name="empresa_id">
                    <option value="">Todas</option>
                    <?php foreach ($empresas as $empresa) : ?>
                        <option value="<?= e((string) $empresa['id']) ?>"<?= $selecionado('empresa_id', (string) $empresa['id']) ?>><?= e($empresa['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4 col-xl-3">
                <label class="form-label" for="tipo_id">Tipo</label>
                <select class="form-select" id="tipo_id" name="tipo_id">
                    <option value="">Todos</option>
                    <?php foreach ($tipos as $tipo) : ?>
                        <option value="<?= e((string) $tipo['id']) ?>"<?= $selecionado('tipo_id', (string) $tipo['id']) ?>><?= e($tipo['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4 col-xl-3">
                <label class="form-label" for="status">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">Todos</option>
                    <?php foreach ($rotulosStatus as $codigo => $rotulo) : ?>
                        <option value="<?= e((string) $codigo) ?>"<?= $selecionado('status', (string) $codigo) ?>><?= e((string) $rotulo) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4 col-xl-3">
                <label class="form-label" for="situacao">Situação</label>
                <select class="form-select" id="situacao" name="situacao">
                    <option value="">Todas</option>
                    <?php foreach ($rotulosSituacao as $codigo => $rotulo) : ?>
                        <option value="<?= e((string) $codigo) ?>"<?= $selecionado('situacao', (string) $codigo) ?>><?= e((string) $rotulo) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4 col-xl-3">
                <label class="form-label" for="responsavel">Responsável</label>
                <select class="form-select" id="responsavel" name="responsavel">
                    <option value="">Todos</option>
                    <option value="meus"<?= $selecionado('responsavel', 'meus') ?>>Minhas obrigações</option>
                    <?php foreach ($responsaveis as $responsavel) : ?>
                        <option value="<?= e((string) $responsavel['id']) ?>"<?= $selecionado('responsavel', (string) $responsavel['id']) ?>><?= e($responsavel['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4 col-xl-3">
                <label class="form-label" for="competencia">Competência</label>
                <input class="form-control" id="competencia" name="competencia" type="month" placeholder="AAAA-MM" value="<?= e($valorFiltro('competencia')) ?>">
            </div>
            <div class="col-md-4 col-xl-3">
                <label class="form-label" for="de">Vencimento a partir de</label>
                <input class="form-control" id="de" name="de" type="date" value="<?= e($valorFiltro('de')) ?>">
            </div>
            <div class="col-md-4 col-xl-3">
                <label class="form-label" for="ate">Vencimento até</label>
                <input class="form-control" id="ate" name="ate" type="date" value="<?= e($valorFiltro('ate')) ?>">
            </div>
            <div class="col-md-4 col-xl-3">
                <label class="form-label" for="entregue_de">Entregue a partir de</label>
                <input class="form-control" id="entregue_de" name="entregue_de" type="date" value="<?= e($valorFiltro('entregue_de')) ?>">
            </div>
            <div class="col-md-4 col-xl-3">
                <label class="form-label" for="entregue_ate">Entregue até</label>
                <input class="form-control" id="entregue_ate" name="entregue_ate" type="date" value="<?= e($valorFiltro('entregue_ate')) ?>">
            </div>
            <div class="col-md-4 col-xl-3">
                <label class="form-label" for="ordem">Ordenar por</label>
                <select class="form-select" id="ordem" name="ordem">
                    <?php foreach ($ordens as $codigo => $rotulo) : ?>
                        <option value="<?= e($codigo) ?>"<?= $selecionado('ordem', $codigo) ?>><?= e($rotulo) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="acoes-formulario">
            <button class="btn btn-primary" type="submit">Aplicar filtros</button>
            <a class="btn btn-outline-secondary" href="<?= e(url('/obrigacoes')) ?>">Limpar</a>
        </div>
    </form>

    <?php
    $filtros = $filtrosAtivos;
    require dirname(__DIR__, 2) . '/partials/filtros-ativos.php';
    ?>

    <div class="card cartao-tabela"><div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th scope="col">Empresa</th>
                    <th scope="col">Tipo</th>
                    <th scope="col">Competência</th>
                    <th scope="col">Vencimento</th>
                    <th scope="col">Status</th>
                    <th scope="col">Situação</th>
                    <th scope="col">Responsável</th>
                    <th scope="col"><span class="texto-acessivel">Ações</span></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($resultado->itens as $obrigacao) : ?>
                    <?php
                    $id = (int) ($obrigacao['id'] ?? 0);
                    $texto = static fn (string $campo): string => is_scalar($obrigacao[$campo] ?? null) ? (string) $obrigacao[$campo] : '';
                    $competencia = $texto('competencia');
                    $atrasada = $texto('atrasada') === '1';
                    ?>
                    <tr>
                        <th scope="row"><?= e($texto('empresa_nome')) ?></th>
                        <td><?= e($texto('tipo_nome')) ?></td>
                        <td><?= e(substr($competencia, 5, 2) . '/' . substr($competencia, 0, 4)) ?></td>
                        <td>
                            <?php if ($atrasada) : ?>
                                <strong><?= e(data_br($texto('vencimento'))) ?></strong>
                            <?php else : ?>
                                <?= e(data_br($texto('vencimento'))) ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $seloRotulo = (string) ($rotulosStatus[$texto('status')] ?? $texto('status'));
                            $seloTom = ['ENTREGUE' => 'sucesso', 'EM_ANDAMENTO' => 'info', 'AGUARDANDO_CLIENTE' => 'aviso'][$texto('status')] ?? 'neutro';
                            require dirname(__DIR__, 2) . '/partials/status.php';
                            ?>
                        </td>
                        <td>
                            <?php if ($texto('atraso_critico') === '1') : ?>
                                <span class="semaforo semaforo-vermelho" title="Atraso crítico: superior a 30 dias"><span class="semaforo-ponto" aria-hidden="true"></span>Atrasada (&gt; 30d)</span>
                            <?php elseif ($atrasada) : ?>
                                <span class="semaforo semaforo-vermelho"><span class="semaforo-ponto" aria-hidden="true"></span>Atrasada</span>
                            <?php elseif ($texto('vence_hoje') === '1') : ?>
                                <span class="semaforo semaforo-amarelo"><span class="semaforo-ponto" aria-hidden="true"></span>Vence hoje</span>
                            <?php elseif ($texto('vence_em_breve') === '1') : ?>
                                <?php $dias = (int) $texto('dias_para_vencer'); ?>
                                <span class="semaforo semaforo-amarelo"><span class="semaforo-ponto" aria-hidden="true"></span><?= e($dias === 1 ? 'Vence amanhã' : 'Vence em ' . $dias . ' dias') ?></span>
                            <?php elseif ($texto('entregue_com_atraso') === '1') : ?>
                                <span class="semaforo semaforo-amarelo"><span class="semaforo-ponto" aria-hidden="true"></span>Entregue com atraso</span>
                            <?php else : ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td><?= e($texto('responsavel_nome') !== '' ? $texto('responsavel_nome') : '—') ?></td>
                        <td>
                            <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('/obrigacoes/' . $id)) ?>">
                                <?php $icone = 'ver'; require dirname(__DIR__, 2) . '/partials/icone.php'; ?>
                                <span class="texto-acessivel">Ver <?= e($texto('tipo_nome')) ?> de <?= e($texto('empresa_nome')) ?></span>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($resultado->itens === []) : ?>
                    <tr><td colspan="8">Nenhuma obrigação encontrada.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    </div>

    <?php
    $pagina = $resultado->pagina;
    $totalPaginas = max(1, $resultado->totalPaginas);
    $caminho = '/obrigacoes';
    require dirname(__DIR__, 2) . '/partials/paginacao.php';
    ?>
</section>
