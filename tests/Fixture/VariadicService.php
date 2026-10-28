<?php

declare(strict_types=1);

namespace SuperKernel\Support\Tests\Fixture;

final readonly class VariadicService
{
    /** @var list<Dependency> */
    public array $values;

    public function __construct(Dependency ...$values)
    {
        $this->values = $values;
    }
}
