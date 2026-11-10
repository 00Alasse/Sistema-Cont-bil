<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\View;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

require_once dirname(__DIR__, 3) . '/app/Core/helpers.php';

final class ViewTest extends TestCase
{
    private string $diretorio;

    protected function setUp(): void
    {
        $this->diretorio = sys_get_temp_dir() . '/tributo-view-' . bin2hex(random_bytes(6));
        mkdir($this->diretorio . '/paginas', 0777, true);
        mkdir($this->diretorio . '/layouts', 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->diretorio . '/paginas/*.php') ?: [] as $arquivo) {
            unlink($arquivo);
        }
        foreach (glob($this->diretorio . '/layouts/*.php') ?: [] as $arquivo) {
            unlink($arquivo);
        }
        rmdir($this->diretorio . '/paginas');
        rmdir($this->diretorio . '/layouts');
        rmdir($this->diretorio);
    }

    public function testRenderizaTemplateComDadosDentroDoLayout(): void
    {
        file_put_contents(
            $this->diretorio . '/paginas/inicio.php',
            '<h1><?= e($titulo) ?></h1>'
        );
        file_put_contents(
            $this->diretorio . '/layouts/app.php',
            '<main><?= $conteudo ?></main>'
        );

        $html = (new View($this->diretorio))->render(
            'paginas/inicio',
            ['titulo' => '<Início>'],
            'layouts/app'
        );

        self::assertSame('<main><h1>&lt;Início&gt;</h1></main>', $html);
    }

    public function testLimpaBufferQuandoTemplateLancaExcecao(): void
    {
        file_put_contents(
            $this->diretorio . '/paginas/falha.php',
            '<?php echo "parcial"; throw new RuntimeException("falha");'
        );
        file_put_contents($this->diretorio . '/layouts/app.php', 'layout');
        $nivelInicial = ob_get_level();

        try {
            (new View($this->diretorio))->render('paginas/falha', [], 'layouts/app');
            self::fail('Era esperado que a falha do template fosse propagada.');
        } catch (RuntimeException $exception) {
            self::assertSame('falha', $exception->getMessage());
        }

        self::assertSame($nivelInicial, ob_get_level());
    }

    public function testRejeitaCaminhoDeTemplateForaDaPastaDeViews(): void
    {
        $view = new View($this->diretorio);

        $this->expectException(InvalidArgumentException::class);
        $view->render('../fora', [], 'layouts/app');
    }
}
