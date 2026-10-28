<?php

declare(strict_types=1);

namespace SuperKernel\Support\Tests\Fixture;

final readonly class DefaultedService
{
    public function __construct(public ?Dependency $dependency = null)
    {
    }
}
