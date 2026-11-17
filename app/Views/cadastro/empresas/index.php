<?php

declare(strict_types=1);

use App\Core\Pagina;
use App\Support\Cnpj;

$resultado = $paginaEmpresas ?? null;
$resultado = $resultado instanceof Pagina ? $resultado : new Pagina([], 0, 1, 20);
$filtros = is_array($filtros ?? null) ? $filtros : [];
$responsaveis = is_array($responsaveis ?? null) ? $responsaveis : [];
$filtrosAtivos = is_array($filtrosAtivos ?? null) ? $filtrosAtivos : [];
$queryAtual = is_array($queryAtual ?? null) ? $queryAtual : [];
$regimes = ['MEI' => 'MEI', 'SIMPLES_NACIONAL' => 'Simples Nacional', 'LUCRO_PRESUMIDO' => 'Lucro Presumido', 'LUCRO_REAL' => 'Lucro Real'];
$valorFiltro = static fn (string $campo): string => is_scalar($filtros[$campo] ?? null)
    ? (string) $filtros[$campo]
    : '';
?>
<section class="pagina">
    <header class="pagina-cabecalho">
        <div>
            <h1>Empresas</h1>
            <p>Consulte e mantenha os cadastros do escritório.</p>
        </div>
        <a class="btn btn-primary" href="<?= e(url('/empresas/nova')) ?>">
            <?php $icone = 'adicionar'; require dirname(__DIR__, 2) . '/partials/icone.php'; ?>
            Cadastrar empresa
        </a>
    </header>

    <form class="card card-body formulario-filtros" method="get" action="<?= e(url('/empresas')) ?>">
        <div class="row g-3">
            <div class="col-12">
                <label class="form-label" for="q">Busca</label>
                <input class="form-control" id="q" name="q" type="search" value="<?= e($valorFiltro('q')) ?>"
                    placeholder="Razão social, nome fantasia ou CNPJ">
            </div>
            <div class="col-md-4 col-xl-3">
                <label class="form-label" for="status">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">Todos</option>
                    <option value="ATIVA"<?= $valorFiltro('status') === 'ATIVA' ? ' selected' : '' ?>>Ativa</option>
                    <option value="INATIVA"<?= $valorFiltro('status') === 'INATIVA' ? ' selected' : '' ?>>Inativa</option>
                </select>
            </div>
            <div class="col-md-4 col-xl-3">
                <label class="form-label" for="regime">Regime tributário</label>
                <select class="form-select" id="regime" name="regime">
                    <option value="">Todos</option>
                    <?php foreach ([
                        'MEI' => 'MEI',
                        'SIMPLES_NACIONAL' => 'Simples Nacional',
                        'LUCRO_PRESUMIDO' => 'Lucro Presumido',
                        'LUCRO_REAL' => 'Lucro Real',
                    ] as $codigo => $rotulo) : ?>
                        <option value="<?= e($codigo) ?>"<?= $valorFiltro('regime') === $codigo ? ' selected' : '' ?>>
                            <?= e($rotulo) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4 col-xl-3">
                <label class="form-label" for="responsavel_id">Responsável</label>
                <select class="form-select" id="responsavel_id" name="responsavel_id">
                    <option value="">Todos</option>
                    <?php foreach ($responsaveis as $responsavel) : ?>
                        <?php if (is_array($responsavel)) : ?>
                            <?php $idResponsavel = (int) ($responsavel['id'] ?? 0); ?>
                            <option value="<?= e((string) $idResponsavel) ?>"
                                <?= $valorFiltro('responsavel_id') === (string) $idResponsavel ? 'selected' : '' ?>>
                                <?= e(is_string($responsavel['nome'] ?? null) ? $responsavel['nome'] : '') ?>
                            </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4 col-xl-3">
                <label class="form-label" for="servico">Serviço</label>
                <select class="form-select" id="servico" name="servico">
                    <option value="">Todos</option>
                    <option value="bpo"<?= $valorFiltro('servico') === 'bpo' ? ' selected' : '' ?>>BPO financeiro</option>
                    <option value="consultivo"<?= $valorFiltro('servico') === 'consultivo' ? ' selected' : '' ?>>Consultivo</option>
                </select>
            </div>
            <div class="col-md-4 col-xl-3">
                <label class="form-label" for="ordem">Ordenar por</label>
                <select class="form-select" id="ordem" name="ordem">
                    <option value="razao_social"<?= $valorFiltro('ordem') !== 'criado_em' ? ' selected' : '' ?>>
                        Razão social
                    </option>
                    <option value="criado_em"<?= $valorFiltro('ordem') === 'criado_em' ? ' selected' : '' ?>>
                        Data de criação
                    </option>
                </select>
            </div>
        </div>
        <div class="acoes-formulario">
            <button class="btn btn-primary" type="submit">Aplicar filtros</button>
            <a class="btn btn-outline-secondary" href="<?= e(url('/empresas')) ?>">Limpar</a>
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
                    <th scope="col">Razão social</th>
                    <th scope="col">CNPJ</th>
                    <th scope="col">Regime</th>
                    <th scope="col">Responsável</th>
                    <th scope="col">Status</th>
                    <th scope="col"><span class="texto-acessivel">Ações</span></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($resultado->itens as $empresa) : ?>
                    <?php
                    $id = (int) ($empresa['id'] ?? 0);
                    $razaoSocial = is_string($empresa['razao_social'] ?? null) ? $empresa['razao_social'] : '';
                    $cnpj = is_string($empresa['cnpj'] ?? null) ? $empresa['cnpj'] : '';
                    $regime = is_string($empresa['regime_tributario'] ?? null)
                        ? $empresa['regime_tributario']
                        : '';
                    $responsavelNome = is_string($empresa['responsavel_nome'] ?? null)
                        ? $empresa['responsavel_nome']
                        : '—';
                    $status = is_string($empresa['status'] ?? null) ? $empresa['status'] : '';
                    ?>
                    <tr>
                        <th scope="row"><a href="<?= e(url('/empresas/' . $id)) ?>"><?= e($razaoSocial) ?></a></th>
                        <td class="text-nowrap"><?= e(Cnpj::formatar($cnpj)) ?></td>
                        <td><?= e($regimes[$regime] ?? $regime) ?></td>
                        <td><?= e($responsavelNome) ?></td>
                        <td>
                            <?php
                            $seloRotulo = $status === 'ATIVA' ? 'Ativa' : 'Inativa';
                            $seloTom = $status === 'ATIVA' ? 'sucesso' : 'neutro';
                            require dirname(__DIR__, 2) . '/partials/status.php';
                            ?>
                        </td>
                        <td>
                            <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('/empresas/' . $id)) ?>">
                                <?php $icone = 'ver'; require dirname(__DIR__, 2) . '/partials/icone.php'; ?>
                                Ver<span class="texto-acessivel"> <?= e($razaoSocial) ?></span>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($resultado->itens === []) : ?>
                    <tr><td colspan="6">Nenhuma empresa encontrada.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    </div>

    <?php
    $pagina = $resultado->pagina;
    $totalPaginas = max(1, $resultado->totalPaginas);
    $caminho = '/empresas';
    require dirname(__DIR__, 2) . '/partials/paginacao.php';
    ?>
</section>
