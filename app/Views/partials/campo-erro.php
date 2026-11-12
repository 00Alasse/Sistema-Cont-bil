<?php

declare(strict_types=1);

$campoErro = is_string($campo ?? null) ? $campo : '';
/** @var array<string, list<string>> $errosCampo */
$errosCampo = is_array($erros ?? null) ? $erros : [];
?>
<?= erro_campo($errosCampo, $campoErro) ?>
