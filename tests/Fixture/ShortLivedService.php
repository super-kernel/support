<?php

declare(strict_types=1);

namespace SuperKernel\Support\Tests\Fixture;

use stdClass;

final readonly class ShortLivedService
{
    public function __construct(
        public Dependency $dependency,
        public string     $name = 'default',
        public ?stdClass  $metadata = null,
    )
    {
    }
}
