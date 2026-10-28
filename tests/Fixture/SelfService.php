<?php

declare(strict_types=1);

namespace SuperKernel\Support\Tests\Fixture;

readonly class SelfService
{
    public function __construct(public ?self $value = null)
    {
    }
}
