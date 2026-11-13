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
$ordens = [
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
        <a class="botao" href="<?= e(url('/obrigacoes/nova')) ?>">Cadastrar obrigação</a>
    </header>

    <form class="cartao formulario-filtros" method="get" action="<?= e(url('/obrigacoes')) ?>">
        <div class="grade-formulario">
            <div class="campo campo-largo">
                <label for="q">Busca</label>
                <input id="q" name="q" type="search" value="<?= e($valorFiltro('q')) ?>" placeholder="Empresa ou tipo de obrigação">
            </div>
            <div class="campo">
                <label for="empresa_id">Empresa</label>
                <select id="empresa_id" name="empresa_id">
                    <option value="">Todas</option>
                    <?php foreach ($empresas as $empresa) : ?>
                        <option value="<?= e((string) $empresa['id']) ?>"<?= $selecionado('empresa_id', (string) $empresa['id']) ?>><?= e($empresa['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="campo">
                <label for="tipo_id">Tipo</label>
                <select id="tipo_id" name="tipo_id">
                    <option value="">Todos</option>
                    <?php foreach ($tipos as $tipo) : ?>
                        <option value="<?= e((string) $tipo['id']) ?>"<?= $selecionado('tipo_id', (string) $tipo['id']) ?>><?= e($tipo['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="campo">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="">Todos</option>
                    <?php foreach ($rotulosStatus as $codigo => $rotulo) : ?>
                        <option value="<?= e((string) $codigo) ?>"<?= $selecionado('status', (string) $codigo) ?>><?= e((string) $rotulo) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="campo">
                <label for="situacao">Situação</label>
                <select id="situacao" name="situacao">
                    <option value="">Todas</option>
                    <?php foreach ($rotulosSituacao as $codigo => $rotulo) : ?>
                        <option value="<?= e((string) $codigo) ?>"<?= $selecionado('situacao', (string) $codigo) ?>><?= e((string) $rotulo) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="campo">
                <label for="responsavel">Responsável</label>
                <select id="responsavel" name="responsavel">
                    <option value="">Todos</option>
                    <option value="meus"<?= $selecionado('responsavel', 'meus') ?>>Minhas obrigações</option>
                    <?php foreach ($responsaveis as $responsavel) : ?>
                        <option value="<?= e((string) $responsavel['id']) ?>"<?= $selecionado('responsavel', (string) $responsavel['id']) ?>><?= e($responsavel['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="campo">
                <label for="competencia">Competência</label>
                <input id="competencia" name="competencia" type="month" placeholder="AAAA-MM" value="<?= e($valorFiltro('competencia')) ?>">
            </div>
            <div class="campo">
                <label for="de">Vencimento a partir de</label>
                <input id="de" name="de" type="date" value="<?= e($valorFiltro('de')) ?>">
            </div>
            <div class="campo">
                <label for="ate">Vencimento até</label>
                <input id="ate" name="ate" type="date" value="<?= e($valorFiltro('ate')) ?>">
            </div>
            <div class="campo">
                <label for="ordem">Ordenar por</label>
                <select id="ordem" name="ordem">
                    <?php foreach ($ordens as $codigo => $rotulo) : ?>
                        <option value="<?= e($codigo) ?>"<?= $selecionado('ordem', $codigo) ?>><?= e($rotulo) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="acoes-formulario">
            <button class="botao" type="submit">Aplicar filtros</button>
            <a class="botao botao-secundario" href="<?= e(url('/obrigacoes')) ?>">Limpar</a>
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
                        <td><?= e((string) ($rotulosStatus[$texto('status')] ?? $texto('status'))) ?></td>
                        <td>
                            <?php if ($atrasada) : ?>
                                <span class="semaforo semaforo-vermelho"><span class="semaforo-ponto" aria-hidden="true"></span>Atrasada</span>
                            <?php elseif ($texto('entregue_com_atraso') === '1') : ?>
                                <span class="semaforo semaforo-amarelo"><span class="semaforo-ponto" aria-hidden="true"></span>Entregue com atraso</span>
                            <?php else : ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td><?= e($texto('responsavel_nome') !== '' ? $texto('responsavel_nome') : '—') ?></td>
                        <td><a href="<?= e(url('/obrigacoes/' . $id)) ?>">Ver</a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($resultado->itens === []) : ?>
                    <tr><td colspan="8">Nenhuma obrigação encontrada.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php
    $pagina = $resultado->pagina;
    $totalPaginas = max(1, $resultado->totalPaginas);
    $caminho = '/obrigacoes';
    require dirname(__DIR__, 2) . '/partials/paginacao.php';
    ?>
</section>
