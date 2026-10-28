<?php

declare(strict_types=1);

namespace SuperKernel\Support\Tests\Fixture;

use LogicException;
use Psr\Container\ContainerInterface;
use Throwable;

use function array_key_exists;

class RecordingContainer implements ContainerInterface
{
    /** @var list<array{string, string}> */
    public array $calls = [];

    /** @param array<string, mixed> $entries */
    public function __construct(
        private readonly array $entries = [],
        public ?Throwable      $hasFailure = null,
        public ?Throwable      $getFailure = null,
    )
    {
    }

    /** @throws Throwable When the configured failure is exercised or the entry is absent. */
    public function get(string $id): mixed
    {
        $this->calls[] = [
            'get',
            $id,
        ];
        if (null !== $this->getFailure) {
            throw $this->getFailure;
        }
        if (!array_key_exists($id, $this->entries)) {
            throw new LogicException('Unexpected container lookup: ' . $id);
        }
        return $this->entries[$id];
    }

    /** @throws Throwable When the configured lookup failure is exercised. */
    public function has(string $id): bool
    {
        $this->calls[] = [
            'has',
            $id,
        ];
        if (null !== $this->hasFailure) {
            throw $this->hasFailure;
        }
        return array_key_exists($id, $this->entries);
    }
}
