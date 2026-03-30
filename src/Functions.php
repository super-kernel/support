<?php
declare(strict_types=1);

namespace SuperKernel\Support;

use SuperKernel\Context\ApplicationContext;
use SuperKernel\Contract\ContainerInterface;
use SuperKernel\Di\Definition\ObjectDefinition;
use SuperKernel\Di\Resolver\ObjectResolver;
use function array_values;

/**
 * Create an object instance.
 * This function attempts to resolve the dependency via the DI container if available in the ApplicationContext. If the
 * container is unavailable or resolution fails, it falls back to manual instantiation using the `new` keyword.
 *
 * @template TClass
 *
 * @param string $id
 * @param array  $parameters
 *
 * @return ($id is class-string<TClass> ? TClass : mixed)
 */
function make(string $id, array $parameters = []): mixed
{
	if (ApplicationContext::hasContainer()) {
		$container = ApplicationContext::getContainer();
		if ($container instanceof ContainerInterface) {
			$definition = new ObjectDefinition($id);
			return $container->get(ObjectResolver::class)->resolve($definition, $parameters);
		}
	}
	return new $id(...array_values($parameters));
}