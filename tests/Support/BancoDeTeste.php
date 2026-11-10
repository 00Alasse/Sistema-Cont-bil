<?php

declare(strict_types=1);

namespace Tests\Support;

use RuntimeException;

final class BancoDeTeste
{
    private static bool $preparado = false;

    public static function preparar(): void
    {
        if (self::$preparado) {
            return;
        }

        $raiz = dirname(__DIR__, 2);
        $comando = [
            PHP_BINARY,
            $raiz . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'db.php',
            'rebuild',
            '--test',
            '--sem-seed',
        ];
        $processo = proc_open(
            $comando,
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            $raiz
        );

        if (!is_resource($processo)) {
            throw new RuntimeException('Não foi possível iniciar a preparação do banco de teste.');
        }

        fclose($pipes[0]);
        $saida = stream_get_contents($pipes[1]);
        $erro = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $codigo = proc_close($processo);

        if ($codigo !== 0) {
            throw new RuntimeException(
                'Não foi possível preparar tributo_test. ' . trim((string) $saida . ' ' . (string) $erro)
            );
        }

        self::$preparado = true;
    }
}
