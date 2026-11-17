<?php

declare(strict_types=1);

/*
 * Moldura de um cartão de módulo (App\Core\CartaoPainel), incluída por index.php.
 * O template vem da classe do cartão (código, não da requisição) e recebe $dados.
 */
$cartaoAtual = is_array($cartao ?? null) ? $cartao : [];
$templateCartao = is_string($cartaoAtual['template'] ?? null) ? $cartaoAtual['template'] : '';
$arquivoCartao = dirname(__DIR__, 2) . '/' . $templateCartao . '.php';
$dados = is_array($cartaoAtual['dados'] ?? null) ? $cartaoAtual['dados'] : [];
?>
<section class="card card-body cartao-modulo">
    <h2><?= e((string) ($cartaoAtual['titulo'] ?? '')) ?></h2>
    <?php if (preg_match('#\A[a-z0-9_-]+(/[a-z0-9_-]+)*\z#', $templateCartao) === 1 && is_file($arquivoCartao)) : ?>
        <?php require $arquivoCartao; ?>
    <?php endif; ?>
</section>
