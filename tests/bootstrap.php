<?php

declare(strict_types=1);

/**
 * Inicialização sem conexão com banco para manter a suíte unitária independente.
 * A suíte de integração prepara tributo_test na classe-base compartilhada.
 */

require dirname(__DIR__) . '/vendor/autoload.php';

date_default_timezone_set('America/Sao_Paulo');
