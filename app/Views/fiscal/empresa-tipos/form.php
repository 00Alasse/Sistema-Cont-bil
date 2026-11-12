<?php

declare(strict_types=1);

use App\Core\UsuarioLogado;

$empresa = is_array($empresa ?? null) ? $empresa : [];
$empresaId = (int) ($empresa['id'] ?? 0);
$linhas = is_array($linhas ?? null) ? $linhas : [];
$responsaveis = is_array($responsaveis ?? null) ? $responsaveis : [];
$erros = is_array($erros ?? null) ? $erros : [];
$usuarioAdmin = ($usuario ?? null) instanceof UsuarioLogado && $usuario->ehAdmin();
$razaoSocial = is_string($empresa['razao_social'] ?? null) ? $empresa['razao_social'] : '';
$rotulos = ['tipos' => 'Tipos', 'responsavel_padrao' => 'Responsável padrão'];
?>
<section class="pagina">
    <header class="pagina-cabecalho">
        <div>
            <h1><?= e($razaoSocial) ?></h1>
            <p>Tipos de obrigação que se aplicam a esta empresa. A escolha é do escritório.</p>
        </div>
        <a class="botao botao-secundario" href="<?= e(url('/empresas')) ?>">Voltar à lista</a>
    </header>

    <?php require dirname(__DIR__, 2) . '/partials/abas-empresa.php'; ?>

    <form class="cartao formulario" method="post" action="<?= e(url('/empresas/' . $empresaId . '/tipos-obrigacao')) ?>">
        <?= csrf_campo() ?>
        <?php if ($erros !== []) : ?>
            <div class="aviso aviso-erro" role="alert">
                <p>Revise os vínculos:</p>
                <ul>
                    <?php foreach ($erros as $campoErro => $mensagensErro) : ?>
                        <?php foreach (is_array($mensagensErro) ? $mensagensErro : [] as $mensagemErro) : ?>
                            <?php if (is_string($mensagemErro)) : ?>
                                <li><strong><?= e($rotulos[$campoErro] ?? (string) $campoErro) ?>:</strong> <?= e($mensagemErro) ?></li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($linhas === []) : ?>
            <p>
                Nenhum tipo de obrigação ativo cadastrado.
                <?php if ($usuarioAdmin) : ?>
                    <a href="<?= e(url('/tipos-obrigacao/novo')) ?>">Cadastrar tipo</a>
                <?php endif; ?>
            </p>
        <?php else : ?>
            <div class="tabela-responsiva">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">Aplica-se</th>
                            <th scope="col">Tipo</th>
                            <th scope="col">Periodicidade</th>
                            <th scope="col">Responsável padrão</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($linhas as $linha) : ?>
                            <?php
                            $tipoId = (int) ($linha['id'] ?? 0);
                            $nome = is_string($linha['nome'] ?? null) ? $linha['nome'] : '';
                            $periodicidade = is_string($linha['periodicidade'] ?? null) ? $linha['periodicidade'] : '';
                            $marcado = (int) ($linha['vinculado'] ?? 0) === 1;
                            $inativo = (int) ($linha['ativo'] ?? 1) === 0;
                            $responsavelAtual = is_scalar($linha['responsavel_padrao_id'] ?? null)
                                ? (string) $linha['responsavel_padrao_id']
                                : '';
                            $idCampo = 'tipo-' . $tipoId;
                            ?>
                            <tr>
                                <td>
                                    <input id="<?= e($idCampo) ?>" type="checkbox" name="tipos[]"
                                        value="<?= e((string) $tipoId) ?>"<?= $marcado ? ' checked' : '' ?>>
                                </td>
                                <th scope="row">
                                    <label for="<?= e($idCampo) ?>"><?= e($nome) ?></label>
                                    <?php if ($inativo) : ?>
                                        <small>(inativo: pode ser mantido, mas não incluído de novo)</small>
                                    <?php endif; ?>
                                </th>
                                <td><?= e($periodicidade) ?></td>
                                <td>
                                    <label class="texto-acessivel" for="responsavel-<?= e((string) $tipoId) ?>">
                                        Responsável padrão de <?= e($nome) ?>
                                    </label>
                                    <select id="responsavel-<?= e((string) $tipoId) ?>"
                                        name="responsavel_padrao[<?= e((string) $tipoId) ?>]">
                                        <option value="">Responsável da empresa</option>
                                        <?php foreach ($responsaveis as $responsavel) : ?>
                                            <?php if (is_array($responsavel)) : ?>
                                                <?php $idResponsavel = (string) ($responsavel['id'] ?? ''); ?>
                                                <option value="<?= e($idResponsavel) ?>"<?= $responsavelAtual === $idResponsavel ? ' selected' : '' ?>>
                                                    <?= e(is_string($responsavel['nome'] ?? null) ? $responsavel['nome'] : '') ?>
                                                </option>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <div class="acoes-formulario">
            <button class="botao" type="submit">Salvar tipos da empresa</button>
        </div>
    </form>
</section>
