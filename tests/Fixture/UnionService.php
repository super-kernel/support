<?php

declare(strict_types=1);

namespace SuperKernel\Support\Tests\Fixture;

final readonly class UnionService
{
    public function __construct(public DependencyInterface|AlternateDependencyInterface $dependency)
    {
    }
}
