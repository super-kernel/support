<?php

declare(strict_types=1);

namespace SuperKernel\Support\Tests\Unit;

use Error;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use SuperKernel\Context\ApplicationContext;
use SuperKernel\Di\Definition\MethodDefinition;
use SuperKernel\Di\Exception\ResolutionException;
use SuperKernel\Di\Resolver\MethodResolver;
use SuperKernel\Support\Tests\Fixture\AbstractService;
use SuperKernel\Support\Tests\Fixture\AlternateDependencyInterface;
use SuperKernel\Support\Tests\Fixture\BuiltinService;
use SuperKernel\Support\Tests\Fixture\BuiltinUnionService;
use SuperKernel\Support\Tests\Fixture\ChildParentService;
use SuperKernel\Support\Tests\Fixture\ChildSelfService;
use SuperKernel\Support\Tests\Fixture\Choice;
use SuperKernel\Support\Tests\Fixture\DefaultedService;
use SuperKernel\Support\Tests\Fixture\DefaultedUnionService;
use SuperKernel\Support\Tests\Fixture\Dependency;
use SuperKernel\Support\Tests\Fixture\DependencyInterface;
use SuperKernel\Support\Tests\Fixture\DnfService;
use SuperKernel\Support\Tests\Fixture\EnumService;
use SuperKernel\Support\Tests\Fixture\InterfaceService;
use SuperKernel\Support\Tests\Fixture\IntersectionService;
use SuperKernel\Support\Tests\Fixture\MixedService;
use SuperKernel\Support\Tests\Fixture\NullableService;
use SuperKernel\Support\Tests\Fixture\ObjectService;
use SuperKernel\Support\Tests\Fixture\ParentDependency;
use SuperKernel\Support\Tests\Fixture\ParentService;
use SuperKernel\Support\Tests\Fixture\PrivateConstructorService;
use SuperKernel\Support\Tests\Fixture\RecordingContainer;
use SuperKernel\Support\Tests\Fixture\RecordingContractContainer;
use SuperKernel\Support\Tests\Fixture\ReferenceService;
use SuperKernel\Support\Tests\Fixture\ReferenceVariadicService;
use SuperKernel\Support\Tests\Fixture\SelfService;
use SuperKernel\Support\Tests\Fixture\SelfUnionService;
use SuperKernel\Support\Tests\Fixture\ShortLivedService;
use SuperKernel\Support\Tests\Fixture\UnionService;
use SuperKernel\Support\Tests\Fixture\UntypedService;
use SuperKernel\Support\Tests\Fixture\VariadicService;
use Throwable;
use TypeError;

use function get_object_vars;
use function SuperKernel\Support\make;

final class ConstructorArgumentsTest extends TestCase
{
    /**
     * @param class-string                 $className
     * @param array<int|string, mixed>     $parameters
     * @param array<string, mixed>         $entries
     * @param array<string, mixed>         $properties
     * @param list<array{string, string}>  $calls
     * @param class-string<Throwable>|null $failure
     */
    #[DataProvider('scenarios')]
    public function testMatchesMethodResolver(
        string  $className,
        array   $parameters,
        array   $entries,
        array   $properties,
        array   $calls,
        ?string $failure = null,
    ): void
    {
        $psr = new RecordingContainer($entries);
        $contract = new RecordingContractContainer($entries);
        ApplicationContext::setContainer($psr);
        $made = $resolved = null;
        $makeFailure = $diFailure = null;

        try {
            $made = make($className, $parameters);
        } catch (Throwable $throwable) {
            $makeFailure = $throwable;
        }
        try {
            $arguments = new MethodResolver($contract)->resolve(new MethodDefinition($className, null, $parameters));
            $resolved = new $className(...$arguments);
        } catch (Throwable $throwable) {
            $diFailure = $throwable;
        }

        self::assertSame($calls, $psr->calls, 'Support lookup order');
        self::assertSame($calls, $contract->calls, 'DI lookup order');
        if (null !== $failure) {
            self::assertNotNull($makeFailure);
            self::assertNotNull($diFailure);
            self::assertSame($failure, $makeFailure::class);
            self::assertSame(
                InvalidArgumentException::class === $failure ? ResolutionException::class : $failure,
                $diFailure::class,
                'Parameter selection failures deliberately have different owners.',
            );
            return;
        }

        self::assertNull($makeFailure, $makeFailure?->getMessage() ?? '');
        self::assertNull($diFailure, $diFailure?->getMessage() ?? '');
        self::assertIsObject($made);
        self::assertIsObject($resolved);
        self::assertSame($properties, get_object_vars($made));
        self::assertSame($properties, get_object_vars($resolved));
        self::assertNotSame($made, $resolved);
    }

    #[DataProvider('lookupFailures')]
    public function testLookupFailuresMatchWithoutChangingIdentity(bool $failHas): void
    {
        $failure = new RuntimeException('Container lookup failed.');
        $entries = [
            DependencyInterface::class => new Dependency(),
        ];
        $psr = new RecordingContainer($entries, $failHas ? $failure : null, $failHas ? null : $failure);
        $contract = new RecordingContractContainer($entries, $failHas ? $failure : null, $failHas ? null : $failure);
        ApplicationContext::setContainer($psr);
        $madeFailure = $resolvedFailure = null;
        try {
            make(DefaultedUnionService::class);
        } catch (Throwable $caught) {
            $madeFailure = $caught;
        }
        try {
            new MethodResolver($contract)->resolve(new MethodDefinition(DefaultedUnionService::class));
        } catch (Throwable $caught) {
            $resolvedFailure = $caught;
        }
        self::assertSame($failure, $madeFailure);
        self::assertSame($failure, $resolvedFailure);
        $calls = [
            [
                'has',
                DependencyInterface::class,
            ],
        ];
        if (!$failHas) {
            $calls[] = [
                'has',
                AlternateDependencyInterface::class,
            ];
            $calls[] = [
                'get',
                DependencyInterface::class,
            ];
        }
        self::assertSame($calls, $psr->calls);
        self::assertSame($calls, $contract->calls);
    }

    /** @return iterable<string, array{bool}> */
    public static function lookupFailures(): iterable
    {
        yield 'has fails' => [
            true,
        ];
        yield 'get fails' => [
            false,
        ];
    }

    /**
     * @return iterable<string, array{
     *     0: class-string, 1: array<int|string, mixed>, 2: array<string, mixed>, 3: array<string, mixed>,
     *     4: list<array{string, string}>, 5?: class-string<Throwable>
     * }>
     */
    public static function scenarios(): iterable
    {
        $dependency = new Dependency();
        $other = new Dependency();
        $object = new stdClass();
        $interface = DependencyInterface::class;
        $alternate = AlternateDependencyInterface::class;
        $interfaceCalls = [
            [
                'has',
                $interface,
            ],
            [
                'get',
                $interface,
            ],
        ];
        $unionHas = [
            [
                'has',
                $interface,
            ],
            [
                'has',
                $alternate,
            ],
        ];
        $interfaceProperties = [
            'dependency' => $dependency,
            'name'       => 'default',
        ];

        yield 'interface dependency' => [
            InterfaceService::class,
            [],
            [
                $interface => $dependency,
            ],
            $interfaceProperties,
            $interfaceCalls,
        ];
        yield 'concrete dependency' => [
            ShortLivedService::class,
            [
                'metadata' => null,
            ],
            [
                Dependency::class => $dependency,
            ],
            [
                'dependency' => $dependency,
                'name'       => 'default',
                'metadata'   => null,
            ],
            [
                [
                    'has',
                    Dependency::class,
                ],
                [
                    'get',
                    Dependency::class,
                ],
            ],
        ];
        yield 'name wins over position' => [
            InterfaceService::class,
            [
                0            => $other,
                'dependency' => $dependency,
            ],
            [],
            $interfaceProperties,
            [],
        ];
        yield 'position override' => [
            InterfaceService::class,
            [
                $dependency,
                'position',
            ],
            [],
            [
                'dependency' => $dependency,
                'name'       => 'position',
            ],
            [],
        ];
        yield 'explicit null is provided' => [
            NullableService::class,
            [
                'dependency' => null,
            ],
            [
                Dependency::class => $dependency,
            ],
            [
                'dependency' => null,
            ],
            [],
        ];
        yield 'invalid explicit value is a native error' => [
            InterfaceService::class,
            [
                'dependency' => null,
            ],
            [
                $interface => $dependency,
            ],
            [],
            [],
            TypeError::class,
        ];
        yield 'required dependency missing' => [
            InterfaceService::class,
            [],
            [],
            [],
            [
                [
                    'has',
                    $interface,
                ],
            ],
            InvalidArgumentException::class,
        ];
        yield 'default after missing service' => [
            DefaultedService::class,
            [],
            [],
            [
                'dependency' => null,
            ],
            [
                [
                    'has',
                    Dependency::class,
                ],
            ],
        ];
        yield 'service before default' => [
            DefaultedService::class,
            [],
            [
                Dependency::class => $dependency,
            ],
            [
                'dependency' => $dependency,
            ],
            [
                [
                    'has',
                    Dependency::class,
                ],
                [
                    'get',
                    Dependency::class,
                ],
            ],
        ];
        yield 'nullable without default' => [
            NullableService::class,
            [],
            [],
            [
                'dependency' => null,
            ],
            [
                [
                    'has',
                    Dependency::class,
                ],
            ],
        ];
        yield 'container may return null' => [
            NullableService::class,
            [],
            [
                Dependency::class => null,
            ],
            [
                'dependency' => null,
            ],
            [
                [
                    'has',
                    Dependency::class,
                ],
                [
                    'get',
                    Dependency::class,
                ],
            ],
        ];
        yield 'first union candidate' => [
            UnionService::class,
            [],
            [
                $interface => $dependency,
            ],
            [
                'dependency' => $dependency,
            ],
            [
                ...$unionHas,
                [
                    'get',
                    $interface,
                ],
            ],
        ];
        yield 'second union candidate' => [
            UnionService::class,
            [],
            [
                $alternate => $dependency,
            ],
            [
                'dependency' => $dependency,
            ],
            [
                ...$unionHas,
                [
                    'get',
                    $alternate,
                ],
            ],
        ];
        yield 'union missing' => [
            UnionService::class,
            [],
            [],
            [],
            $unionHas,
            InvalidArgumentException::class,
        ];
        yield 'same object still ambiguous' => [
            UnionService::class,
            [],
            [
                $interface => $dependency,
                $alternate => $dependency,
            ],
            [],
            $unionHas,
            InvalidArgumentException::class,
        ];
        yield 'different objects ambiguous' => [
            UnionService::class,
            [],
            [
                $interface => $dependency,
                $alternate => $other,
            ],
            [],
            $unionHas,
            InvalidArgumentException::class,
        ];
        yield 'ambiguity precedes default' => [
            DefaultedUnionService::class,
            [],
            [
                $interface => $dependency,
                $alternate => $other,
            ],
            [],
            $unionHas,
            InvalidArgumentException::class,
        ];
        yield 'union nullable default' => [
            DefaultedUnionService::class,
            [],
            [],
            [
                'dependency' => null,
            ],
            $unionHas,
        ];
        yield 'explicit union bypasses ambiguity' => [
            UnionService::class,
            [
                'dependency' => $dependency,
            ],
            [
                $interface => $dependency,
                $alternate => $other,
            ],
            [
                'dependency' => $dependency,
            ],
            [],
        ];
        yield 'builtin union branch ignored' => [
            BuiltinUnionService::class,
            [],
            [
                'string' => 'ignored',
            ],
            [
                'dependency' => 'fallback',
            ],
            [
                [
                    'has',
                    $interface,
                ],
            ],
        ];
        yield 'named union branch before scalar default' => [
            BuiltinUnionService::class,
            [],
            [
                $interface => $dependency,
            ],
            [
                'dependency' => $dependency,
            ],
            $interfaceCalls,
        ];
        yield 'explicit scalar union branch' => [
            BuiltinUnionService::class,
            [
                'value',
            ],
            [
                $interface => $dependency,
            ],
            [
                'dependency' => 'value',
            ],
            [],
        ];
        yield 'intersection is not a candidate' => [
            IntersectionService::class,
            [],
            [
                $interface                    => $dependency,
                $alternate                    => $dependency,
                $interface . '&' . $alternate => $dependency,
            ],
            [],
            [],
            InvalidArgumentException::class,
        ];
        yield 'explicit intersection' => [
            IntersectionService::class,
            [
                $dependency,
            ],
            [],
            [
                'dependency' => $dependency,
            ],
            [],
        ];
        yield 'DNF uses named branch only' => [
            DnfService::class,
            [],
            [
                stdClass::class => $object,
                $interface      => $dependency,
                $alternate      => $dependency,
            ],
            [
                'dependency' => $object,
            ],
            [
                [
                    'has',
                    stdClass::class,
                ],
                [
                    'get',
                    stdClass::class,
                ],
            ],
        ];
        yield 'DNF does not resolve intersection branch' => [
            DnfService::class,
            [],
            [
                $interface => $dependency,
                $alternate => $dependency,
            ],
            [],
            [
                [
                    'has',
                    stdClass::class,
                ],
            ],
            InvalidArgumentException::class,
        ];
        yield 'builtin defaults' => [
            BuiltinService::class,
            [],
            [],
            [
                'arguments' => [
                    7,
                    'default',
                    false,
                    [],
                    null,
                    null,
                ],
            ],
            [],
        ];
        $callback = static fn(): string => 'value';
        $builtins = [
            8,
            'explicit',
            true,
            [
                'item',
            ],
            $callback,
            $object,
        ];
        yield 'builtin explicit values' => [
            BuiltinService::class,
            $builtins,
            [],
            [
                'arguments' => $builtins,
            ],
            [],
        ];
        yield 'builtin and parameter-name IDs ignored' => [
            BuiltinService::class,
            [],
            [
                'count'  => 99,
                'int'    => 99,
                'string' => 'ignored',
                'object' => $object,
            ],
            [
                'arguments' => [
                    7,
                    'default',
                    false,
                    [],
                    null,
                    null,
                ],
            ],
            [],
        ];
        yield 'mixed permits null' => [
            MixedService::class,
            [],
            [],
            [
                'value' => null,
            ],
            [],
        ];
        yield 'required untyped is unresolved' => [
            UntypedService::class,
            [],
            [],
            [],
            [],
            InvalidArgumentException::class,
        ];
        yield 'explicit untyped' => [
            UntypedService::class,
            [
                'value' => $object,
            ],
            [],
            [
                'value' => $object,
            ],
            [],
        ];
        yield 'object type is builtin' => [
            ObjectService::class,
            [],
            [
                'object' => $object,
            ],
            [],
            [],
            InvalidArgumentException::class,
        ];
        yield 'positional variadics ignored' => [
            VariadicService::class,
            [
                $dependency,
                $other,
            ],
            [],
            [
                'values' => [],
            ],
            [],
        ];
        yield 'named variadics ignored' => [
            VariadicService::class,
            [
                'values' => $dependency,
            ],
            [],
            [
                'values' => [],
            ],
            [],
        ];
        yield 'reference rejected before explicit value' => [
            ReferenceService::class,
            [
                'value' => $object,
            ],
            [],
            [],
            [],
            InvalidArgumentException::class,
        ];
        yield 'reference missing also rejected' => [
            ReferenceService::class,
            [],
            [],
            [],
            [],
            InvalidArgumentException::class,
        ];
        yield 'reference variadic skipped' => [
            ReferenceVariadicService::class,
            [
                'value',
            ],
            [],
            [
                'values' => [],
            ],
            [],
        ];
        $self = new SelfService();
        $child = new ChildSelfService();
        yield 'self is requested class' => [
            SelfService::class,
            [],
            [
                SelfService::class => $self,
            ],
            [
                'value' => $self,
            ],
            [
                [
                    'has',
                    SelfService::class,
                ],
                [
                    'get',
                    SelfService::class,
                ],
            ],
        ];
        yield 'inherited self is requested child' => [
            ChildSelfService::class,
            [],
            [
                ChildSelfService::class => $child,
            ],
            [
                'value' => $child,
            ],
            [
                [
                    'has',
                    ChildSelfService::class,
                ],
                [
                    'get',
                    ChildSelfService::class,
                ],
            ],
        ];
        yield 'inherited self does not look up declaring class' => [
            ChildSelfService::class,
            [],
            [
                SelfService::class => $self,
            ],
            [
                'value' => null,
            ],
            [
                [
                    'has',
                    ChildSelfService::class,
                ],
            ],
        ];
        $parent = new ParentDependency();
        $parentService = new ParentService();
        yield 'parent is direct parent' => [
            ParentService::class,
            [],
            [
                ParentDependency::class => $parent,
            ],
            [
                'value' => $parent,
            ],
            [
                [
                    'has',
                    ParentDependency::class,
                ],
                [
                    'get',
                    ParentDependency::class,
                ],
            ],
        ];
        yield 'inherited parent is requested direct parent' => [
            ChildParentService::class,
            [],
            [
                ParentService::class => $parentService,
            ],
            [
                'value' => $parentService,
            ],
            [
                [
                    'has',
                    ParentService::class,
                ],
                [
                    'get',
                    ParentService::class,
                ],
            ],
        ];
        yield 'inherited parent ignores original parent' => [
            ChildParentService::class,
            [],
            [
                ParentDependency::class => $parent,
            ],
            [
                'value' => null,
            ],
            [
                [
                    'has',
                    ParentService::class,
                ],
            ],
        ];
        $selfUnion = new SelfUnionService();
        yield 'duplicate candidate IDs queried once' => [
            SelfUnionService::class,
            [],
            [
                SelfUnionService::class => $selfUnion,
            ],
            [
                'value' => $selfUnion,
            ],
            [
                [
                    'has',
                    SelfUnionService::class,
                ],
                [
                    'get',
                    SelfUnionService::class,
                ],
            ],
        ];
        yield 'enum is a named candidate' => [
            EnumService::class,
            [],
            [
                Choice::class => Choice::First,
            ],
            [
                'choice' => Choice::First,
            ],
            [
                [
                    'has',
                    Choice::class,
                ],
                [
                    'get',
                    Choice::class,
                ],
            ],
        ];
        yield 'no constructor ignores parameters' => [
            Dependency::class,
            [
                'unknown' => 1,
                9         => $object,
            ],
            [],
            [],
            [],
        ];
        yield 'unknown named key ignored' => [
            BuiltinService::class,
            [
                'unknown' => 'ignored',
            ],
            [],
            [
                'arguments' => [
                    7,
                    'default',
                    false,
                    [],
                    null,
                    null,
                ],
            ],
            [],
        ];
        yield 'extra positional values ignored' => [
            InterfaceService::class,
            [
                $dependency,
                'default',
                'ignored',
            ],
            [],
            $interfaceProperties,
            [],
        ];
        yield 'invalid container value is a native error' => [
            InterfaceService::class,
            [],
            [
                $interface => $object,
            ],
            [],
            $interfaceCalls,
            TypeError::class,
        ];
        yield 'interface target fails natively' => [
            DependencyInterface::class,
            [],
            [],
            [],
            [],
            Error::class,
        ];
        yield 'private constructor fails natively' => [
            PrivateConstructorService::class,
            [],
            [],
            [],
            [],
            Error::class,
        ];
        yield 'abstract target fails natively' => [
            AbstractService::class,
            [],
            [],
            [],
            [],
            Error::class,
        ];
    }
}
