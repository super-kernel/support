<?php

declare(strict_types=1);

namespace SuperKernel\Support\Tests\Fixture;

use Throwable;

final class ThrowingService
{
    /** @throws Throwable The supplied constructor failure propagates to the caller. */
    public function __construct(Throwable $failure)
    {
        throw $failure;
    }
}
