<?php

declare(strict_types=1);

namespace SuperKernel\Support\Tests\Fixture;

final readonly class BuiltinUnionService
{
    public function __construct(public DependencyInterface|string $dependency = 'fallback')
    {
    }
}
