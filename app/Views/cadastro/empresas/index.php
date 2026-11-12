<?php

declare(strict_types=1);

use App\Core\Pagina;

$resultado = $paginaEmpresas ?? null;
$resultado = $resultado instanceof Pagina ? $resultado : new Pagina([], 0, 1, 20);
$filtros = is_array($filtros ?? null) ? $filtros : [];
$responsaveis = is_array($responsaveis ?? null) ? $responsaveis : [];
$filtrosAtivos = is_array($filtrosAtivos ?? null) ? $filtrosAtivos : [];
$queryAtual = is_array($queryAtual ?? null) ? $queryAtual : [];
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
        <a class="botao" href="<?= e(url('/empresas/nova')) ?>">Cadastrar empresa</a>
    </header>

    <form class="cartao formulario-filtros" method="get" action="<?= e(url('/empresas')) ?>">
        <div class="grade-formulario">
            <div class="campo campo-largo">
                <label for="q">Busca</label>
                <input id="q" name="q" type="search" value="<?= e($valorFiltro('q')) ?>"
                    placeholder="Razão social, nome fantasia ou CNPJ">
            </div>
            <div class="campo">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="">Todos</option>
                    <option value="ATIVA"<?= $valorFiltro('status') === 'ATIVA' ? ' selected' : '' ?>>Ativa</option>
                    <option value="INATIVA"<?= $valorFiltro('status') === 'INATIVA' ? ' selected' : '' ?>>Inativa</option>
                </select>
            </div>
            <div class="campo">
                <label for="regime">Regime tributário</label>
                <select id="regime" name="regime">
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
            <div class="campo">
                <label for="responsavel_id">Responsável</label>
                <select id="responsavel_id" name="responsavel_id">
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
            <div class="campo">
                <label for="servico">Serviço</label>
                <select id="servico" name="servico">
                    <option value="">Todos</option>
                    <option value="bpo"<?= $valorFiltro('servico') === 'bpo' ? ' selected' : '' ?>>BPO financeiro</option>
                    <option value="consultivo"<?= $valorFiltro('servico') === 'consultivo' ? ' selected' : '' ?>>Consultivo</option>
                </select>
            </div>
            <div class="campo">
                <label for="ordem">Ordenar por</label>
                <select id="ordem" name="ordem">
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
            <button class="botao" type="submit">Aplicar filtros</button>
            <a class="botao botao-secundario" href="<?= e(url('/empresas')) ?>">Limpar</a>
        </div>
    </form>

    <?php
    $filtros = $filtrosAtivos;
    require dirname(__DIR__, 2) . '/partials/filtros-ativos.php';
    ?>

    <div class="cartao tabela-responsiva">
        <table>
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
                        <td><?= e($cnpj) ?></td>
                        <td><?= e($regime) ?></td>
                        <td><?= e($responsavelNome) ?></td>
                        <td><?= e($status === 'ATIVA' ? 'Ativa' : 'Inativa') ?></td>
                        <td><a href="<?= e(url('/empresas/' . $id)) ?>">Ver</a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($resultado->itens === []) : ?>
                    <tr><td colspan="6">Nenhuma empresa encontrada.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php
    $pagina = $resultado->pagina;
    $totalPaginas = max(1, $resultado->totalPaginas);
    $caminho = '/empresas';
    require dirname(__DIR__, 2) . '/partials/paginacao.php';
    ?>
</section>
