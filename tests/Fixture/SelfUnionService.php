<?php

declare(strict_types=1);

namespace SuperKernel\Support\Tests\Fixture;

final readonly class SelfUnionService
{
    public function __construct(public self|SelfUnionService|null $value = null)
    {
    }
}
