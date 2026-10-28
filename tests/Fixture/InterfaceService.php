<?php

declare(strict_types=1);

namespace SuperKernel\Support\Tests\Fixture;

final readonly class InterfaceService
{
    public function __construct(public DependencyInterface $dependency, public string $name = 'default')
    {
    }
}
