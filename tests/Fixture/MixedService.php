<?php

declare(strict_types=1);

namespace SuperKernel\Support\Tests\Fixture;

final readonly class MixedService
{
    public function __construct(public mixed $value)
    {
    }
}
