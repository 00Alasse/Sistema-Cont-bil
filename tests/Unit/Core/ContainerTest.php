<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Container;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ContainerTest extends TestCase
{
    public function testInstanciaClasseSemDependencias(): void
    {
        self::assertInstanceOf(ServicoSemDependencias::class, (new Container())->get(ServicoSemDependencias::class));
    }

    public function testResolveDependenciasEncadeadas(): void
    {
        $servico = (new Container())->get(ServicoEncadeado::class);

        self::assertInstanceOf(ServicoEncadeado::class, $servico);
        self::assertInstanceOf(RepositorioEncadeado::class, $servico->repositorio);
        self::assertInstanceOf(DependenciaEncadeada::class, $servico->repositorio->dependencia);
    }

    public function testUsaInstanciaPreviamenteRegistrada(): void
    {
        $container = new Container();
        $instancia = new DependenciaEncadeada();
        $container->definir(DependenciaEncadeada::class, $instancia);

        self::assertSame($instancia, $container->get(DependenciaEncadeada::class));
    }

    public function testFactoryCallableRecebeContainerERegistraResultado(): void
    {
        $container = new Container();
        $container->definir(
            'factory',
            static fn (Container $container): object => $container->get(DependenciaEncadeada::class)
        );
        $instancia = $container->get('factory');

        self::assertInstanceOf(DependenciaEncadeada::class, $instancia);
        self::assertSame($instancia, $container->get('factory'));
    }

    public function testDependenciaEscalarObrigatoriaNaoPodeSerResolvida(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('parâmetro');

        (new Container())->get(ServicoComEscalarObrigatorio::class);
    }

    public function testDependenciaCircularGeraMensagemClara(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('circular');

        (new Container())->get(ServicoCircularA::class);
    }

    public function testClasseInexistenteGeraMensagemClara(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('não existe');

        (new Container())->get('App\\NaoExiste');
    }
}

final class ServicoSemDependencias
{
}

final class DependenciaEncadeada
{
}

final class RepositorioEncadeado
{
    public function __construct(public readonly DependenciaEncadeada $dependencia)
    {
    }
}

final class ServicoEncadeado
{
    public function __construct(public readonly RepositorioEncadeado $repositorio)
    {
    }
}

final class ServicoComEscalarObrigatorio
{
    public function __construct(public readonly string $nome)
    {
    }
}

final class ServicoCircularA
{
    public function __construct(public readonly ServicoCircularB $dependencia)
    {
    }
}

final class ServicoCircularB
{
    public function __construct(public readonly ServicoCircularA $dependencia)
    {
    }
}
