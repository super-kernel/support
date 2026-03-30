<?php
declare(strict_types=1);

namespace SuperKernelTest\Support;

final readonly class TestCase
{
	public function __construct(private string $name)
	{
	}

	public function getName(): string
	{
		return $this->name;
	}
}