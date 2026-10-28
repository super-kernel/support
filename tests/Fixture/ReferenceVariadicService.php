<?php

declare(strict_types=1);

namespace SuperKernel\Support\Tests\Fixture;

final readonly class ReferenceVariadicService
{
    /** @var list<mixed> */
    public array $values;

    /**
     * The reference signature deliberately exercises constructor selection boundaries.
     *
     * @noinspection PhpParameterByRefIsNotUsedAsReferenceInspection
     */
    public function __construct(mixed &...$values)
    {
        $this->values = $values;
    }
}
