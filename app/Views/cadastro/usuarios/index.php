<?php

declare(strict_types=1);

$usuarios = is_array($usuarios ?? null) ? $usuarios : [];
$rotulosPerfil = [
    'ADMIN' => 'Administrador',
    'COLABORADOR' => 'Colaborador',
    'CLIENTE' => 'Cliente',
];
?>
<section class="pagina">
    <header class="pagina-cabecalho">
        <div>
            <h1>Usuários</h1>
            <p>Equipe do escritório e usuários com acesso à plataforma.</p>
        </div>
    </header>

    <div class="card cartao-tabela"><div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th scope="col">Nome</th>
                    <th scope="col">E-mail</th>
                    <th scope="col">Perfil</th>
                    <th scope="col">Empresa</th>
                    <th scope="col">Situação</th>
                    <th scope="col">Último acesso</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($usuarios as $u) : ?>
                    <?php
                    $ativo = (int) ($u['ativo'] ?? 0) === 1;
                    $perfil = (string) ($u['perfil'] ?? '');
                    $ultimoAcesso = (string) ($u['ultimo_login_em'] ?? '');
                    $empresaNome = (string) ($u['empresa_nome'] ?? '');
                    ?>
                    <tr>
                        <th scope="row"><strong><?= e((string) $u['nome']) ?></strong></th>
                        <td><?= e((string) $u['email']) ?></td>
                        <td><?= e($rotulosPerfil[$perfil] ?? $perfil) ?></td>
                        <td><?= e($empresaNome !== '' ? $empresaNome : '—') ?></td>
                        <td>
                            <?php if ($ativo) : ?>
                                <span class="semaforo semaforo-verde"><span class="semaforo-ponto" aria-hidden="true"></span>Ativo</span>
                            <?php else : ?>
                                <span class="semaforo semaforo-vermelho"><span class="semaforo-ponto" aria-hidden="true"></span>Inativo</span>
                            <?php endif; ?>
                        </td>
                        <td><?= e($ultimoAcesso !== '' ? data_br(substr($ultimoAcesso, 0, 10)) . ' ' . substr($ultimoAcesso, 11, 5) : 'Nunca acessou') ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($usuarios === []) : ?>
                    <tr><td colspan="6">Nenhum usuário cadastrado.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>
</section>
