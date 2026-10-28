<?php

declare(strict_types=1);

namespace SuperKernel\Support\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionException;
use SuperKernel\Context\ApplicationContext;
use SuperKernel\Context\Exception\ContainerNotSetException;

use function dirname;
use function fclose;
use function proc_close;
use function proc_open;
use function stream_get_contents;
use function var_export;

use const PHP_BINARY;

final class StandaloneTest extends TestCase
{
    public function testRequiresOnlyFunctionsForNativeConstruction(): void
    {
        $this->assertIsolatedSuccess(<<<'PHP'
            require $functions;
            $prototype = new class('seed') {
                public function __construct(public string $value) {}
            };
            $result = \SuperKernel\Support\make($prototype::class, ['value' => 'native']);
            if ('native' !== $result->value || class_exists('SuperKernel\\Context\\ApplicationContext', false)) {
                throw new \RuntimeException('Native standalone construction failed.');
            }
            PHP);
    }

    /** @throws ReflectionException When fixture dependency source paths cannot be located. */
    public function testContextWithoutContainerOrDiRejectsConstruction(): void
    {
        $this->assertIsolatedSuccess($this->contextSources() . <<<'PHP'
            require $functions;
            try {
                \SuperKernel\Support\make(\stdClass::class);
            } catch (\SuperKernel\Context\Exception\ContainerNotSetException $failure) {
                if ('The application context has no container.' !== $failure->getMessage()) {
                    throw new \RuntimeException('Unexpected Context error message.');
                }
                exit(0);
            }
            throw new \RuntimeException('Context must reject an unset container without DI.');
            PHP);
    }

    /** @throws ReflectionException When fixture dependency source paths cannot be located. */
    public function testContextFailureIsNotWrappedByMake(): void
    {
        $this->assertIsolatedSuccess($this->contextSources() . <<<'PHP'
            require $functions;
            $failure = new \RuntimeException('Context provider discovery failed.');
            spl_autoload_register(static function (string $class) use ($failure): void {
                if ('SuperKernel\\Di\\Provider\\ContainerProvider' === $class) {
                    throw $failure;
                }
            });
            try {
                \SuperKernel\Support\make(\stdClass::class);
            } catch (\Throwable $caught) {
                if ($caught !== $failure) {
                    throw new \RuntimeException('Context failure identity changed.');
                }
                exit(0);
            }
            throw new \RuntimeException('Context failure must propagate.');
            PHP);
    }

    /** @throws ReflectionException When fixture dependency source paths cannot be located. */
    private function contextSources(): string
    {
        $code = '';
        foreach ([
            ContainerInterface::class,
            ApplicationContext::class,
            ContainerNotSetException::class,
        ] as $class) {
            $code .= 'require ' . var_export(new ReflectionClass($class)->getFileName(), true) . ";\n";
        }
        return $code;
    }

    private function assertIsolatedSuccess(string $code): void
    {
        $source = '$functions = ' . var_export(dirname(__DIR__, 2) . '/src/functions.php', true) . ";\n" . $code;
        $process = proc_open([
            PHP_BINARY,
            '-n',
            '-r',
            $source,
        ], [
            0 => [
                'pipe',
                'r',
            ],
            1 => [
                'pipe',
                'w',
            ],
            2 => [
                'pipe',
                'w',
            ],
            ], $pipes);
        self::assertIsResource($process);
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process), $stdout . $stderr);
        self::assertSame('', $stdout);
        self::assertSame('', $stderr);
    }
}
