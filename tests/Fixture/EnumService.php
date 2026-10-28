<?php

declare(strict_types=1);

namespace SuperKernel\Support\Tests\Fixture;

final readonly class EnumService
{
    public function __construct(public Choice $choice)
    {
    }
}
