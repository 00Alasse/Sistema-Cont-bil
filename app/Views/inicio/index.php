<?php declare(strict_types=1); ?>
<section class="cartao-acesso pagina-erro">
    <?php $marcaVariante = 'clara'; require dirname(__DIR__) . '/partials/marca.php'; ?>
    <h1>Gestão administrativa para escritórios de contabilidade</h1>
    <p>Organize empresas, obrigações e prazos do escritório em um só lugar.</p>
    <a class="btn btn-primary" href="<?= e(url('/login')) ?>">Entrar</a>
    <p class="painel-nota">Ferramenta de controle administrativo e gerencial. Não atesta regularidade fiscal.</p>
</section>