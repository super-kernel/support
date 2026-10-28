<?php

declare(strict_types=1);

namespace SuperKernel\Support\Tests\Fixture;

final readonly class UntypedService
{
    public mixed $value;

    /**
     * @param mixed $value the missing native parameter type is the scenario under test
     *
     * @noinspection PhpMissingParamTypeInspection
     */
    public function __construct($value)
    {
        $this->value = $value;
    }
}
