<?php

declare(strict_types=1);

namespace SuperKernel\Support\Tests\Fixture;

use LogicException;
use Psr\Container\NotFoundExceptionInterface;

final class MissingEntryException extends LogicException implements NotFoundExceptionInterface
{
}
