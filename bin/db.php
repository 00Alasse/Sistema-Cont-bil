<?php

declare(strict_types=1);

use App\Core\Config;

const DESTINOS_REBUILD = [
    'normal' => 'tributo',
    'teste' => 'tributo_test',
];
/**
 * @return array{comando: string, teste: bool, sem_seed: bool}
 */
function interpretarArgumentos(array $argumentos): array
{
    $comando = array_shift($argumentos);
    if (!is_string($comando) || !in_array($comando, ['rebuild', 'build'], true)) {
        throw new RuntimeException(usoDb());
    }

    $teste = false;
    $semSeed = false;
    foreach ($argumentos as $argumento) {
        if ($comando !== 'rebuild') {
            throw new RuntimeException(usoDb());
        }

        if ($argumento === '--test' && !$teste) {
            $teste = true;
            continue;
        }

        if ($argumento === '--sem-seed' && !$semSeed) {
            $semSeed = true;
            continue;
        }

        throw new RuntimeException(usoDb());
    }

    return ['comando' => $comando, 'teste' => $teste, 'sem_seed' => $semSeed];
}

function usoDb(): string
{
    return 'Uso: php bin/db.php rebuild [--test] [--sem-seed] | php bin/db.php build';
}

function destinoBanco(bool $teste): string
{
    return DESTINOS_REBUILD[$teste ? 'teste' : 'normal'];
}

/**
 * @return list<string>
 */
function arquivosSql(string $diretorio): array
{
    $arquivos = glob($diretorio . DIRECTORY_SEPARATOR . '*.sql');
    if ($arquivos === false) {
        throw new RuntimeException('Não foi possível procurar arquivos SQL em ' . $diretorio);
    }

    usort(
        $arquivos,
        static fn (string $a, string $b): int => strnatcasecmp(basename($a), basename($b))
    );

    return $arquivos;
}

function executarArquivoSql(PDO $conexao, string $arquivo): void
{
    $conteudo = file_get_contents($arquivo);
    if ($conteudo === false) {
        throw new RuntimeException('Não foi possível ler o arquivo SQL: ' . basename($arquivo));
    }

    $buffer = '';
    foreach (preg_split('/\R/', $conteudo) ?: [] as $linha) {
        $buffer .= $linha . PHP_EOL;
        if (preg_match('/;\s*\z/', rtrim($linha)) !== 1) {
            continue;
        }

        $comando = trim($buffer);
        $buffer = '';
        if ($comando !== '') {
            $conexao->exec($comando);
        }
    }

    if (trim($buffer) !== '') {
        throw new RuntimeException('O arquivo SQL termina sem delimitador: ' . basename($arquivo));
    }
}

/**
 * @param list<string> $arquivos
 */
function executarArquivosSql(PDO $conexao, array $arquivos): void
{
    foreach ($arquivos as $arquivo) {
        executarArquivoSql($conexao, $arquivo);
    }
}

function conectarSemBanco(): PDO
{
    $host = (string) Config::obter('db.host');
    $porta = (int) Config::obter('db.porta');
    $charset = (string) Config::obter('db.charset');
    $dsn = "mysql:host={$host};port={$porta};charset={$charset}";

    return new PDO(
        $dsn,
        (string) Config::obter('db.usuario'),
        (string) Config::obter('db.senha'),
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
}

/**
 * @param list<string> $schemas
 * @param list<string> $seeds
 */
function reconstruirBanco(bool $teste, bool $semSeed, array $schemas, array $seeds): void
{
    $banco = destinoBanco($teste);
    require_once dirname(__DIR__) . '/vendor/autoload.php';
    if (Config::obter('app.env') === 'production') {
        throw new RuntimeException('O comando rebuild não pode ser executado em produção.');
    }

    $conexao = conectarSemBanco();
    $identificador = '`' . $banco . '`';
    fwrite(STDOUT, 'Reconstruindo somente o banco autorizado: ' . $banco . PHP_EOL);
    $conexao->exec('DROP DATABASE IF EXISTS ' . $identificador);
    $conexao->exec(
        'CREATE DATABASE ' . $identificador . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
    );
    $conexao->exec('USE ' . $identificador);
    executarArquivosSql($conexao, $schemas);

    if (!$semSeed) {
        executarArquivosSql($conexao, $seeds);
    }
}

/**
 * @param list<string> $arquivosSchema
 * @param list<string> $arquivosSeed
 */
function gerarSqlConsolidado(array $arquivosSchema, array $arquivosSeed): string
{
    if ($arquivosSchema === []) {
        throw new RuntimeException('Nenhum arquivo de schema foi encontrado.');
    }

    $tabelas = [];
    foreach ($arquivosSchema as $arquivo) {
        $conteudo = file_get_contents($arquivo);
        if ($conteudo === false) {
            throw new RuntimeException('Não foi possível ler o arquivo SQL: ' . basename($arquivo));
        }

        preg_match_all(
            '/\bCREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?([a-zA-Z0-9_]+)`?/i',
            $conteudo,
            $correspondencias
        );
        foreach ($correspondencias[1] as $tabela) {
            $tabelas[$tabela] = true;
        }
    }

    $secoes = [
        '-- -Tributo! - SQL consolidado gerado; não editar à mão.',
        '-- Gerado em: ' . date('Y-m-d'),
        'SET FOREIGN_KEY_CHECKS=0;',
    ];
    foreach (array_reverse(array_keys($tabelas)) as $tabela) {
        $secoes[] = 'DROP TABLE IF EXISTS `' . $tabela . '`;';
    }

    foreach (array_merge($arquivosSchema, $arquivosSeed) as $arquivo) {
        $conteudo = file_get_contents($arquivo);
        if ($conteudo === false) {
            throw new RuntimeException('Não foi possível ler o arquivo SQL: ' . basename($arquivo));
        }

        $secoes[] = '-- Fonte: ' . basename($arquivo);
        $secoes[] = trim($conteudo);
    }

    $secoes[] = 'SET FOREIGN_KEY_CHECKS=1;';

    return implode(PHP_EOL . PHP_EOL, $secoes) . PHP_EOL;
}

/**
 * @param list<string> $arquivosSchema
 * @param list<string> $arquivosSeed
 */
function construirSql(string $destino, array $arquivosSchema, array $arquivosSeed): void
{
    $sql = gerarSqlConsolidado($arquivosSchema, $arquivosSeed);
    if (file_put_contents($destino, $sql, LOCK_EX) === false) {
        throw new RuntimeException('Não foi possível gravar database/tributo.sql.');
    }

    fwrite(STDOUT, 'SQL consolidado atualizado: ' . $destino . PHP_EOL);
}

/**
 * @param list<string> $argumentos
 */
function executarComandoDb(array $argumentos): int
{
    $opcoes = interpretarArgumentos($argumentos);
    $raiz = dirname(__DIR__);
    $schemas = arquivosSql($raiz . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'schema');
    $seeds = arquivosSql($raiz . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'seeds');

    if ($opcoes['comando'] === 'build') {
        construirSql(
            $raiz . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'tributo.sql',
            $schemas,
            $seeds
        );

        return 0;
    }

    if ($schemas === []) {
        throw new RuntimeException('Nenhum arquivo de schema foi encontrado.');
    }

    if (!$opcoes['sem_seed'] && $seeds === []) {
        throw new RuntimeException('Nenhum arquivo de seed foi encontrado.');
    }

    reconstruirBanco(
        $opcoes['teste'],
        $opcoes['sem_seed'],
        $schemas,
        $seeds
    );
    fwrite(STDOUT, 'Banco preparado com sucesso.' . PHP_EOL);
    return 0;
}

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    try {
        exit(executarComandoDb(array_slice($argv, 1)));
    } catch (Throwable $erro) {
        fwrite(STDERR, $erro->getMessage() . PHP_EOL);
        exit(1);
    }
}
