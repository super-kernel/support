<?php

declare(strict_types=1);

namespace SuperKernel\Support\Tests\Architecture;

use JsonException;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use ReflectionFunction;

use function array_keys;
use function dirname;
use function file_get_contents;
use function json_decode;
use function realpath;

use const JSON_THROW_ON_ERROR;

final class PackageContractTest extends TestCase
{
    /**
     * @throws JsonException when the component manifest contains invalid JSON
     */
    public function testKeepsProductionRequirementsLimitedToPhpAndAutoloadsTheFunctionFile(): void
    {
        $manifest = json_decode(
            (string)file_get_contents(dirname(__DIR__, 2) . '/composer.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        self::assertSame([
            'php',
        ], array_keys($manifest['require']));
        self::assertSame('>=8.4.27 <8.5', $manifest['require']['php']);
        self::assertSame([
            'src/functions.php',
        ], $manifest['autoload']['files']);
        self::assertSame([
            'super-kernel/context' => 'Provides the current application container for container-assisted construction.',
        ], $manifest['suggest']);
        self::assertArrayHasKey('super-kernel/context', $manifest['require-dev']);
        self::assertArrayHasKey('super-kernel/contract', $manifest['require-dev']);
        self::assertArrayHasKey('super-kernel/di', $manifest['require-dev']);
    }

    /**
     * @throws ReflectionException when the function or a parameter default cannot be inspected
     */
    public function testExposesTheNamespacedMakeFunctionFromTheDeclaredSourceFile(): void
    {
        $function = new ReflectionFunction('SuperKernel\\Support\\make');

        self::assertSame(
            realpath(dirname(__DIR__, 2) . '/src/functions.php'),
            realpath((string)$function->getFileName()),
        );
        self::assertStringContainsString('@template TClass of object', (string)$function->getDocComment());
        self::assertStringContainsString('string|class-string<TClass>', (string)$function->getDocComment());
        self::assertStringContainsString(
            '@return ($className is class-string<TClass> ? TClass : mixed)',
            (string)$function->getDocComment(),
        );
        self::assertSame('mixed', (string)$function->getReturnType());
        self::assertSame('className', $function->getParameters()[0]->getName());
        self::assertSame('parameters', $function->getParameters()[1]->getName());
        self::assertSame([], $function->getParameters()[1]->getDefaultValue());
    }
}
