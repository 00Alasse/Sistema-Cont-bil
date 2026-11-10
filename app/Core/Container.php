<?php

declare(strict_types=1);

namespace App\Core;

use Closure;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use RuntimeException;
use Throwable;

final class Container
{
    /**
     * @var array<string, object|callable>
     */
    private array $definicoes = [];

    /**
     * @var array<string, object>
     */
    private array $instancias = [];

    /**
     * @var list<string>
     */
    private array $resolvendo = [];

    public function get(string $classe): object
    {
        if (isset($this->instancias[$classe])) {
            return $this->instancias[$classe];
        }

        if (in_array($classe, $this->resolvendo, true)) {
            $ciclo = implode(' -> ', array_merge($this->resolvendo, [$classe]));
            throw new RuntimeException('Dependência circular detectada: ' . $ciclo);
        }

        $this->resolvendo[] = $classe;
        try {
            $instancia = $this->definicoes[$classe] ?? null;
            if ($instancia instanceof Closure || is_array($instancia) || is_string($instancia)) {
                $resolvida = $this->executarFactory($instancia);
                $this->instancias[$classe] = $resolvida;

                return $resolvida;
            }

            if (is_object($instancia)) {
                $this->instancias[$classe] = $instancia;

                return $instancia;
            }

            return $this->construir($classe);
        } finally {
            array_pop($this->resolvendo);
        }
    }

    public function definir(string $classe, object|callable $instancia): void
    {
        if ($classe === '') {
            throw new InvalidArgumentException('O nome da classe não pode ser vazio.');
        }

        unset($this->instancias[$classe]);
        $this->definicoes[$classe] = $instancia;
    }

    private function construir(string $classe): object
    {
        if (!class_exists($classe)) {
            throw new RuntimeException('A classe ' . $classe . ' não existe.');
        }

        $reflexao = new ReflectionClass($classe);
        if (!$reflexao->isInstantiable()) {
            throw new RuntimeException('A classe ' . $classe . ' não pode ser instanciada.');
        }

        $construtor = $reflexao->getConstructor();
        if ($construtor === null) {
            return $reflexao->newInstance();
        }

        $argumentos = [];
        foreach ($construtor->getParameters() as $parametro) {
            if ($parametro->isVariadic()) {
                continue;
            }

            $argumentos[] = $this->resolverParametro($classe, $parametro);
        }

        return $reflexao->newInstanceArgs($argumentos);
    }

    private function resolverParametro(string $classe, ReflectionParameter $parametro): mixed
    {
        $tipo = $parametro->getType();
        if ($tipo instanceof ReflectionNamedType && !$tipo->isBuiltin()) {
            return $this->get($tipo->getName());
        }

        if ($parametro->isDefaultValueAvailable()) {
            return $parametro->getDefaultValue();
        }

        throw new RuntimeException(
            'Não foi possível resolver o parâmetro $' . $parametro->getName()
            . ' do construtor de ' . $classe . '.'
        );
    }

    /**
     * @param callable $factory
     */
    private function executarFactory(callable $factory): object
    {
        $reflexao = self::refletirCallable($factory);
        if ($reflexao->getNumberOfRequiredParameters() > 1) {
            throw new RuntimeException('A factory do container pode receber no máximo o próprio container.');
        }

        $resultado = $reflexao->getNumberOfParameters() === 0
            ? $factory()
            : $factory($this);
        if (!is_object($resultado)) {
            throw new RuntimeException('A factory do container deve retornar um objeto.');
        }

        return $resultado;
    }

    private static function refletirCallable(callable $factory): ReflectionFunction|ReflectionMethod
    {
        if (is_array($factory)) {
            return new ReflectionMethod($factory[0], $factory[1]);
        }

        if (is_object($factory) && !$factory instanceof \Closure) {
            return new ReflectionMethod($factory, '__invoke');
        }

        if (is_string($factory) && str_contains($factory, '::')) {
            return new ReflectionMethod(...explode('::', $factory, 2));
        }

        return new ReflectionFunction($factory);
    }
}
