<?php

declare(strict_types=1);

/*
 * Histórico de status (FI04), incluído por show.php. Lista simples, lida de logs_atividade,
 * do evento mais recente para o mais antigo.
 */
$historico = is_array($historico ?? null) ? $historico : [];
$rotulosStatus = is_array($rotulosStatus ?? null) ? $rotulosStatus : [];
$rotulo = static fn (?string $status): string => $status === null
    ? '—'
    : (string) ($rotulosStatus[$status] ?? $status);
$ouTracoHistorico = static fn (?string $texto): string => $texto !== null && $texto !== '' ? $texto : '—';
?>
<section class="cartao" aria-labelledby="titulo-historico">
    <h2 id="titulo-historico">Histórico de status</h2>

    <?php if ($historico === []) : ?>
        <p>Nenhuma mudança de status registrada.</p>
    <?php else : ?>
        <div class="tabela-responsiva">
            <table>
                <thead>
                    <tr>
                        <th scope="col">Quando</th>
                        <th scope="col">Quem</th>
                        <th scope="col">Mudança</th>
                        <th scope="col">Justificativa</th>
                        <th scope="col">Protocolo</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($historico as $evento) : ?>
                        <?php $quando = (string) ($evento['criado_em'] ?? ''); ?>
                        <tr>
                            <td><?= e(data_br(substr($quando, 0, 10)) . ' ' . substr($quando, 11, 5)) ?></td>
                            <td><?= e($ouTracoHistorico($evento['usuario_nome'] ?? null)) ?></td>
                            <td><?= e($rotulo($evento['de'] ?? null) . ' → ' . $rotulo($evento['para'] ?? null)) ?></td>
                            <td><?= nl2br(e($ouTracoHistorico($evento['justificativa'] ?? null))) ?></td>
                            <td><?= e($ouTracoHistorico($evento['protocolo'] ?? null)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
