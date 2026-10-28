<?php

declare(strict_types=1);

namespace SuperKernel\Support;

use InvalidArgumentException;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionUnionType;
use SuperKernel\Context\ApplicationContext;

use function array_key_exists;
use function array_unique;
use function class_exists;
use function count;
use function implode;
use function sprintf;

/**
 * Creates a caller-owned short-lived instance with optional container-assisted construction.
 * The caller supplies a constructible class, valid arguments, and a working optional application container.
 * Context, reflection, container, and target-constructor failures propagate unchanged.
 *
 * @template TClass of object
 *
 * @param string|class-string<TClass> $className  concrete class name to construct
 * @param array<int|string, mixed>    $parameters constructor parameter overrides by position or name
 *
 * @return ($className is class-string<TClass> ? TClass : mixed)
 *
 * @throws InvalidArgumentException when a constructor parameter is unresolved, ambiguous, or passed by reference
 *
 * @noinspection PhpUnhandledExceptionInspection, PhpDocMissingThrowsInspection
 */
function make(string $className, array $parameters = []): mixed
{
    if (!class_exists(ApplicationContext::class)) {
        return new $className(...$parameters);
    }

    $container = ApplicationContext::getContainer();
    $class = new ReflectionClass($className);
    $arguments = [];

    foreach ($class->getConstructor()?->getParameters() ?? [] as $parameter) {
        if ($parameter->isVariadic()) {
            continue;
        }

        if ($parameter->isPassedByReference()) {
            throw new InvalidArgumentException(sprintf(
                'Constructor parameter %s::__construct($%s) cannot be resolved because references are unsupported.',
                $class->getName(),
                $parameter->getName(),
            ));
        }

        if (array_key_exists($parameter->getName(), $parameters)) {
            $arguments[] = $parameters[$parameter->getName()];
            continue;
        }

        if (array_key_exists($parameter->getPosition(), $parameters)) {
            $arguments[] = $parameters[$parameter->getPosition()];
            continue;
        }

        $type = $parameter->getType();
        $types = $type instanceof ReflectionUnionType ? $type->getTypes() : [
            $type,
        ];
        $candidates = [];

        foreach ($types as $namedType) {
            if (!$namedType instanceof ReflectionNamedType || $namedType->isBuiltin()) {
                continue;
            }

            $candidate = match ($namedType->getName()) {
                'self',
                'static' => $class->getName(),
                'parent' => ($class->getParentClass() ?: null)?->getName(),
                default  => $namedType->getName(),
            };

            if (null !== $candidate) {
                $candidates[] = $candidate;
            }
        }

        $available = [];

        foreach (array_unique($candidates) as $candidate) {
            if ($container->has($candidate)) {
                $available[] = $candidate;
            }
        }

        $availableCount = count($available);

        if (1 === $availableCount) {
            $arguments[] = $container->get($available[0]);
            continue;
        }

        if (1 < $availableCount) {
            throw new InvalidArgumentException(sprintf(
                'Ambiguous constructor parameter %s::__construct($%s): %s.',
                $class->getName(),
                $parameter->getName(),
                implode(', ', $available),
            ));
        }

        if ($parameter->isDefaultValueAvailable()) {
            $arguments[] = $parameter->getDefaultValue();
            continue;
        }

        if (true === $type?->allowsNull()) {
            $arguments[] = null;
            continue;
        }

        throw new InvalidArgumentException(sprintf(
            'Unable to resolve constructor parameter %s::__construct($%s)%s.',
            $class->getName(),
            $parameter->getName(),
            null === $type ? '' : sprintf(' of type %s', $type),
        ));
    }

    return new $className(...$arguments);
}
