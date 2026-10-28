<?php

declare(strict_types=1);

namespace SuperKernel\Support\Tests\Fixture;

final readonly class ObjectService
{
    public function __construct(public object $value)
    {
    }
}
