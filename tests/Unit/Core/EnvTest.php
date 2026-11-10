<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Env;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class EnvTest extends TestCase
{
    /**
     * @var list<string>
     */
    private array $variaveisTemporarias = [];

    /**
     * @var list<string>
     */
    private array $arquivosTemporarios = [];

    protected function tearDown(): void
    {
        foreach ($this->variaveisTemporarias as $chave) {
            putenv($chave);
            unset($_ENV[$chave], $_SERVER[$chave]);
        }

        foreach ($this->arquivosTemporarios as $arquivo) {
            if (is_file($arquivo)) {
                unlink($arquivo);
            }
        }

        parent::tearDown();
    }

    public function testCarregaComentariosAspasSimplesDuplasELinhasVazias(): void
    {
        $arquivo = $this->criarArquivo(
            "# comentário\n\nF01_ENV_DUPLAS=\"valor duplo\"\nF01_ENV_SIMPLES='valor simples'\nF01_ENV_SEM_ASPAS=valor\n"
        );

        Env::carregar($arquivo);

        self::assertSame('valor duplo', Env::obter('F01_ENV_DUPLAS'));
        self::assertSame('valor simples', Env::obter('F01_ENV_SIMPLES'));
        self::assertSame('valor', Env::obter('F01_ENV_SEM_ASPAS'));
    }

    public function testNaoSobrescreveVariavelJaDefinidaNoAmbiente(): void
    {
        $chave = 'F01_ENV_PREDEFINIDA';
        $this->variaveisTemporarias[] = $chave;
        putenv($chave . '=valor do ambiente');

        $arquivo = $this->criarArquivo($chave . "=valor do arquivo\n");
        Env::carregar($arquivo);

        self::assertSame('valor do ambiente', Env::obter($chave));
    }

    public function testArquivoAusenteApresentaOrientacao(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Crie o arquivo .env a partir de .env.example');

        Env::carregar(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tributo-env-inexistente-' . uniqid());
    }

    private function criarArquivo(string $conteudo): string
    {
        $arquivo = tempnam(sys_get_temp_dir(), 'tributo-env-');
        self::assertNotFalse($arquivo);

        $this->arquivosTemporarios[] = $arquivo;
        self::assertNotFalse(file_put_contents($arquivo, $conteudo));

        return $arquivo;
    }
}
