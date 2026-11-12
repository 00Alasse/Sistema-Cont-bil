<?php

declare(strict_types=1);

use App\Core\Pagina;
use App\Core\UsuarioLogado;

$resultado = $paginaTipos ?? null;
$resultado = $resultado instanceof Pagina ? $resultado : new Pagina([], 0, 1, 20);
$filtros = is_array($filtros ?? null) ? $filtros : [];
$filtrosAtivos = is_array($filtrosAtivos ?? null) ? $filtrosAtivos : [];
$queryAtual = is_array($queryAtual ?? null) ? $queryAtual : [];
$usuarioAdmin = ($usuario ?? null) instanceof UsuarioLogado && $usuario->ehAdmin();
$valorFiltro = static fn (string $campo): string => is_scalar($filtros[$campo] ?? null)
    ? (string) $filtros[$campo]
    : '';
$esferas = [
    'FEDERAL' => 'Federal',
    'ESTADUAL' => 'Estadual',
    'MUNICIPAL' => 'Municipal',
    'TRABALHISTA' => 'Trabalhista',
    'OUTRA' => 'Outra',
];
$periodicidades = ['MENSAL' => 'Mensal', 'TRIMESTRAL' => 'Trimestral', 'ANUAL' => 'Anual', 'EVENTUAL' => 'Eventual'];
?>
<section class="pagina">
    <header class="pagina-cabecalho">
        <div>
            <h1>Tipos de obrigação</h1>
            <p>Catálogo de obrigações acompanhadas pelo escritório.</p>
        </div>
        <?php if ($usuarioAdmin) : ?>
            <a class="botao" href="<?= e(url('/tipos-obrigacao/novo')) ?>">Cadastrar tipo</a>
        <?php endif; ?>
    </header>

    <p class="aviso">
        Os prazos são cadastrados pelo escritório e não são conferidos pelo sistema:
        confira o prazo vigente na fonte oficial.
    </p>

    <form class="cartao formulario-filtros" method="get" action="<?= e(url('/tipos-obrigacao')) ?>">
        <div class="grade-formulario">
            <div class="campo campo-largo">
                <label for="q">Busca</label>
                <input id="q" name="q" type="search" value="<?= e($valorFiltro('q')) ?>" placeholder="Nome ou descrição">
            </div>
            <div class="campo">
                <label for="esfera">Esfera</label>
                <select id="esfera" name="esfera">
                    <option value="">Todas</option>
                    <?php foreach ($esferas as $codigo => $rotulo) : ?>
                        <option value="<?= e($codigo) ?>"<?= $valorFiltro('esfera') === $codigo ? ' selected' : '' ?>><?= e($rotulo) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="campo">
                <label for="periodicidade">Periodicidade</label>
                <select id="periodicidade" name="periodicidade">
                    <option value="">Todas</option>
                    <?php foreach ($periodicidades as $codigo => $rotulo) : ?>
                        <option value="<?= e($codigo) ?>"<?= $valorFiltro('periodicidade') === $codigo ? ' selected' : '' ?>><?= e($rotulo) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="campo">
                <label for="ativo">Situação</label>
                <select id="ativo" name="ativo">
                    <option value="">Todos</option>
                    <option value="1"<?= $valorFiltro('ativo') === '1' ? ' selected' : '' ?>>Ativos</option>
                    <option value="0"<?= $valorFiltro('ativo') === '0' ? ' selected' : '' ?>>Inativos</option>
                </select>
            </div>
        </div>
        <div class="acoes-formulario">
            <button class="botao" type="submit">Aplicar filtros</button>
            <a class="botao botao-secundario" href="<?= e(url('/tipos-obrigacao')) ?>">Limpar</a>
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
                    <th scope="col">Nome</th>
                    <th scope="col">Esfera</th>
                    <th scope="col">Periodicidade</th>
                    <th scope="col">Vencimento</th>
                    <th scope="col">Empresas</th>
                    <th scope="col">Situação</th>
                    <?php if ($usuarioAdmin) : ?>
                        <th scope="col"><span class="texto-acessivel">Ações</span></th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($resultado->itens as $tipo) : ?>
                    <?php
                    $id = (int) ($tipo['id'] ?? 0);
                    $texto = static fn (string $campo): string => is_scalar($tipo[$campo] ?? null) ? (string) $tipo[$campo] : '';
                    $dia = $texto('dia_vencimento');
                    $meses = (int) $texto('meses_apos_competencia');
                    $vencimento = $dia === ''
                        ? 'Sem dia fixo'
                        : 'Dia ' . $dia . ', ' . $meses . ($meses === 1 ? ' mês' : ' meses') . ' após a competência';
                    ?>
                    <tr>
                        <th scope="row">
                            <?= e($texto('nome')) ?>
                            <?php if ($texto('descricao') !== '') : ?>
                                <br><small><?= e($texto('descricao')) ?></small>
                            <?php endif; ?>
                        </th>
                        <td><?= e($esferas[$texto('esfera')] ?? $texto('esfera')) ?></td>
                        <td><?= e($periodicidades[$texto('periodicidade')] ?? $texto('periodicidade')) ?></td>
                        <td><?= e($vencimento) ?></td>
                        <td><?= e($texto('total_empresas')) ?></td>
                        <td><?= e($texto('ativo') === '1' ? 'Ativo' : 'Inativo') ?></td>
                        <?php if ($usuarioAdmin) : ?>
                            <td><a href="<?= e(url('/tipos-obrigacao/' . $id . '/editar')) ?>">Editar</a></td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                <?php if ($resultado->itens === []) : ?>
                    <tr><td colspan="<?= $usuarioAdmin ? '7' : '6' ?>">Nenhum tipo de obrigação encontrado.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php
    $pagina = $resultado->pagina;
    $totalPaginas = max(1, $resultado->totalPaginas);
    $caminho = '/tipos-obrigacao';
    require dirname(__DIR__, 2) . '/partials/paginacao.php';
    ?>
</section>
