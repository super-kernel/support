<?php

declare(strict_types=1);

namespace SuperKernel\Support\Tests\Fixture;

use stdClass;

final readonly class DnfService
{
    public function __construct(public (DependencyInterface&AlternateDependencyInterface)|stdClass $dependency)
    {
    }
}
