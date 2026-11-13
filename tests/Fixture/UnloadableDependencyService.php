<?php

declare(strict_types=1);

namespace SuperKernel\Support\Tests\Fixture;

final readonly class UnloadableDependencyService
{
    public function __construct(public UnloadableDependency $dependency)
    {
    }
}
