<?php

declare(strict_types=1);

namespace SuperKernel\Support\Tests\Fixture;

final readonly class ReferenceService
{
    public mixed $value;

    /**
     * The reference signature deliberately exercises constructor selection boundaries.
     *
     * @noinspection PhpParameterByRefIsNotUsedAsReferenceInspection
     */
    public function __construct(mixed &$value)
    {
        $this->value = $value;
    }
}
