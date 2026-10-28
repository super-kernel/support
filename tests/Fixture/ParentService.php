<?php

declare(strict_types=1);

namespace SuperKernel\Support\Tests\Fixture;

class ParentService extends ParentDependency
{
    public function __construct(public ?parent $value = null)
    {
    }
}
