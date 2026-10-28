<?php

declare(strict_types=1);

namespace SuperKernel\Support\Tests\Fixture;

final readonly class DefaultedUnionService
{
    public function __construct(public DependencyInterface|AlternateDependencyInterface|null $dependency = null)
    {
    }
}
