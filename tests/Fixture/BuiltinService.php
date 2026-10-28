<?php

declare(strict_types=1);

namespace SuperKernel\Support\Tests\Fixture;

use function func_get_args;

final readonly class BuiltinService
{
    /** @var list<mixed> */
    public array $arguments;

    /** @param array<array-key, mixed> $items */
    public function __construct(
        int       $count = 7,
        string    $name = 'default',
        bool      $enabled = false,
        array     $items = [],
        ?callable $callback = null,
        ?object   $object = null,
    )
    {
        $this->arguments = func_get_args();
    }
}
