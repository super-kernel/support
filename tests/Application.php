<?php
declare(strict_types=1);

namespace SuperKernelTest\Support;

use SuperKernel\Attribute\Provider;
use SuperKernel\Contract\ApplicationInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use function SuperKernel\Support\make;

#[Provider(ApplicationInterface::class)]
final class Application extends \Symfony\Component\Console\Application implements ApplicationInterface
{
	public function run(InputInterface|null $input = null, OutputInterface|null $output = null): int
	{

		var_dump(
			make(
				TestCase::class,
				[
					'name' => 'first test',
				],
			)->getName(),
			make(
				TestCase::class,
				[
					'second test',
				],
			)->getName(),
		);

		return 0;
	}
}