<?php

declare(strict_types=1);

/**
 * Verifica a sintaxe de todos os arquivos PHP do projeto usando `php -l`.
 *
 * Uso: composer lint   (ou: php bin/lint.php)
 * Sai com código 1 se algum arquivo tiver erro de sintaxe.
 */

$raiz = dirname(__DIR__);
$pastas = ['app', 'bin', 'config', 'public', 'tests'];

$total = 0;
$falhas = 0;

foreach ($pastas as $pasta) {
    $caminho = $raiz . DIRECTORY_SEPARATOR . $pasta;
    if (!is_dir($caminho)) {
        continue;
    }

    $arquivos = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($caminho, FilesystemIterator::SKIP_DOTS)
    );

    /** @var SplFileInfo $arquivo */
    foreach ($arquivos as $arquivo) {
        if ($arquivo->getExtension() !== 'php') {
            continue;
        }

        $total++;
        $saida = [];
        $comando = escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($arquivo->getPathname()) . ' 2>&1';
        exec($comando, $saida, $codigo);

        if ($codigo !== 0) {
            $falhas++;
            echo implode(PHP_EOL, $saida), PHP_EOL;
        }
    }
}

echo "Arquivos verificados: {$total}. Com erro: {$falhas}.", PHP_EOL;
exit($falhas > 0 ? 1 : 0);
