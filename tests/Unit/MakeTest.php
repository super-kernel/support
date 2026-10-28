<?php

declare(strict_types=1);

namespace SuperKernel\Support\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use RuntimeException;
use SuperKernel\Context\ApplicationContext;
use SuperKernel\Support\Tests\Fixture\AlternateDependencyInterface;
use SuperKernel\Support\Tests\Fixture\DefaultedService;
use SuperKernel\Support\Tests\Fixture\DefaultedUnionService;
use SuperKernel\Support\Tests\Fixture\Dependency;
use SuperKernel\Support\Tests\Fixture\DependencyInterface;
use SuperKernel\Support\Tests\Fixture\InterfaceService;
use SuperKernel\Support\Tests\Fixture\RecordingContainer;
use SuperKernel\Support\Tests\Fixture\ReferenceService;
use SuperKernel\Support\Tests\Fixture\ThrowingService;
use SuperKernel\Support\Tests\Fixture\UnionService;
use SuperKernel\Support\Tests\Fixture\UnresolvableService;
use SuperKernel\Support\Tests\Fixture\UntypedService;
use Throwable;

use function spl_autoload_register;
use function spl_autoload_unregister;
use function SuperKernel\Support\make;

final class MakeTest extends TestCase
{
    public function testCreatesFreshTopLevelInstancesWithSharedPsrContainerDependencies(): void
    {
        $dependency = new Dependency();
        $container = new RecordingContainer([
            DependencyInterface::class => $dependency,
        ]);
        ApplicationContext::setContainer($container);

        $first = make(InterfaceService::class, [
            'name' => 'first',
        ]);
        $second = make(InterfaceService::class, [
            'name' => 'second',
        ]);

        self::assertNotSame($first, $second);
        self::assertSame($dependency, $first->dependency);
        self::assertSame($first->dependency, $second->dependency);
        self::assertSame('first', $first->name);
        self::assertSame('second', $second->name);
        self::assertSame([
            [
                'has',
                DependencyInterface::class,
            ],
            [
                'get',
                DependencyInterface::class,
            ],
            [
                'has',
                DependencyInterface::class,
            ],
            [
                'get',
                DependencyInterface::class,
            ],
            ], $container->calls, 'Neither the target class nor any DI service is queried.');
    }

    public function testExplicitValuesNeverQueryTheContainer(): void
    {
        $failure = new RuntimeException('No lookup is allowed.');
        $container = new RecordingContainer(hasFailure: $failure, getFailure: $failure);
        ApplicationContext::setContainer($container);
        $dependency = new Dependency();

        $instance = make(InterfaceService::class, [
            $dependency,
            'explicit',
        ]);

        self::assertSame($dependency, $instance->dependency);
        self::assertSame('explicit', $instance->name);
        self::assertSame([], $container->calls);
    }

    public function testHasFailureDoesNotFallBackToDefaultOrNull(): void
    {
        $failure = new RuntimeException('has failed');
        $container = new RecordingContainer(hasFailure: $failure);
        ApplicationContext::setContainer($container);

        try {
            make(DefaultedService::class);
        } catch (Throwable $caught) {
            self::assertSame($failure, $caught);
            self::assertSame([
                [
                    'has',
                    Dependency::class,
                ],
            ], $container->calls);
            return;
        }
        self::fail('The lookup failure must propagate.');
    }

    public function testGetFailureDoesNotFallBackToDefaultNullOrAnotherCandidate(): void
    {
        $failure = new RuntimeException('get failed');
        $container = new RecordingContainer([
            DependencyInterface::class => new Dependency(),
        ], getFailure: $failure);
        ApplicationContext::setContainer($container);

        try {
            make(DefaultedUnionService::class);
        } catch (Throwable $caught) {
            self::assertSame($failure, $caught);
            self::assertSame([
                [
                    'has',
                    DependencyInterface::class,
                ],
                [
                    'has',
                    AlternateDependencyInterface::class,
                ],
                [
                    'get',
                    DependencyInterface::class,
                ],
                ], $container->calls);
            return;
        }
        self::fail('The lookup failure must propagate.');
    }

    public function testTargetConstructorFailureKeepsItsIdentity(): void
    {
        $failure = new RuntimeException('construction failed');
        $container = new RecordingContainer();
        ApplicationContext::setContainer($container);

        try {
            make(ThrowingService::class, [
                'failure' => $failure,
            ]);
        } catch (Throwable $caught) {
            self::assertSame($failure, $caught);
            self::assertSame([], $container->calls);
            return;
        }
        self::fail('The constructor failure must propagate.');
    }

    public function testReflectionFailureKeepsItsIdentity(): void
    {
        $failure = new ReflectionException('reflection autoload failed');
        $target = 'SupportReflectionFailureTarget';
        /** @throws ReflectionException When reflection triggers the failing fixture autoloader. */
        $loader = static function (string $class) use ($target, $failure): void {
            if ($target === $class) {
                throw $failure;
            }
        };
        ApplicationContext::setContainer(new RecordingContainer());
        spl_autoload_register($loader);
        try {
            try {
                make($target);
            } catch (Throwable $caught) {
                self::assertSame($failure, $caught);
                return;
            }
            self::fail('The reflection failure must propagate.');
        } finally {
            spl_autoload_unregister($loader);
        }
    }

    public function testMissingTargetIsAReflectionFailure(): void
    {
        ApplicationContext::setContainer(new RecordingContainer());
        $this->expectException(ReflectionException::class);
        make('SupportNonexistentTarget');
    }

    /**
     * @param class-string             $className
     * @param array<int|string, mixed> $parameters
     * @param array<string, mixed>     $entries
     */
    #[DataProvider('ownedFailures')]
    public function testOwnedFailureMessages(
        string $className,
        array  $parameters,
        array  $entries,
        string $message,
    ): void
    {
        ApplicationContext::setContainer(new RecordingContainer($entries));
        try {
            make($className, $parameters);
        } catch (InvalidArgumentException $caught) {
            self::assertSame(InvalidArgumentException::class, $caught::class);
            self::assertSame($message, $caught->getMessage());
            return;
        }
        self::fail('The parameter selection must fail.');
    }

    /** @return iterable<string, array{class-string, array<int|string, mixed>, array<string, mixed>, string}> */
    public static function ownedFailures(): iterable
    {
        yield 'typed unresolved' => [
            UnresolvableService::class,
            [],
            [],
            'Unable to resolve constructor parameter ' . UnresolvableService::class
            . '::__construct($required) of type string.',
        ];
        yield 'untyped unresolved omits type' => [
            UntypedService::class,
            [],
            [],
            'Unable to resolve constructor parameter ' . UntypedService::class . '::__construct($value).',
        ];
        yield 'reference precedes explicit value' => [
            ReferenceService::class,
            [
                'value' => null,
            ],
            [],
            'Constructor parameter ' . ReferenceService::class
            . '::__construct($value) cannot be resolved because references are unsupported.',
        ];
        yield 'ambiguous candidates' => [
            UnionService::class,
            [],
            [
                DependencyInterface::class          => new Dependency(),
                AlternateDependencyInterface::class => new Dependency(),
            ],
            'Ambiguous constructor parameter ' . UnionService::class . '::__construct($dependency): '
            . DependencyInterface::class . ', ' . AlternateDependencyInterface::class . '.',
        ];
    }
}
