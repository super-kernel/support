# SuperKernel Support

`super-kernel/support` provides namespaced convenience functions with PHP 8.4.27 or later in the 8.4.x series as its only production requirement.
It can construct objects independently or use the current application container supplied by optional Context integration.

## Caller-owned construction

```php
use App\Service\Report;

use function SuperKernel\Support\make;

$report = make(Report::class, ['format' => 'json']);
```

Every call creates a fresh top-level object directly with `new`. That instance is neither retrieved from nor retained in
the container; the caller owns its lifetime. Container-provided dependencies keep that container's lifecycle and may be shared.
There is no transient dependency-graph scope, property injection, target Provider/FactoryProvider dispatch, lifecycle
hook, or child container. Container-assisted calls reflect the constructor afresh; Support maintains no reflection cache.

The native signature is `make(string $className, array $parameters = []): mixed`. Its generic PHPDoc return type is
`($className is class-string<TClass> ? TClass : mixed)`, preserving the concrete type for a known class string.
A successful call returns an object; there is no failure sentinel.

## Container-assisted construction

When `ApplicationContext` is available, `make()` obtains its current PSR container. Official DI and third-party
containers use the same argument selection code, which calls only `has()` and `get()`. No DI resolver service is needed.
Dependencies must be queryable by their class or interface's fully qualified name. A service registered only as
`mailer` cannot be inferred to implement `MailerInterface`; register the interface ID or pass the value explicitly.
Support neither enumerates services nor constructs missing dependencies itself.

For example, given an application-created third-party PSR container with an entry for `App\Store::class`:

```php
<?php

declare(strict_types=1);

namespace App;

use Psr\Container\ContainerInterface;
use SuperKernel\Context\ApplicationContext;

use function SuperKernel\Support\make;

interface Store
{
    public function save(string $value): void;
}

final readonly class Job
{
    public function __construct(public Store $store, public string $label = 'default')
    {
    }
}

/** @var ContainerInterface $container The application's configured third-party container. */
ApplicationContext::setContainer($container);
$first = make(Job::class, ['label' => 'first']);
$second = make(Job::class, ['label' => 'second']);
// $first !== $second; Store comes from the container and can be shared.
```

The application bootstraps the third-party container according to that implementation's API before setting Context.
Support does not add a container implementation or an installation requirement for DI or Contract.

### Parameter selection

With Context available, `make()` mirrors DI's constructor argument selection rules:

1. A missing constructor produces an empty argument list, regardless of supplied overrides.
2. Variadic parameters are skipped, including reference variadics and explicitly supplied variadic values.
   Non-variadic reference parameters fail before overrides are read.
3. For each remaining parameter, a named override wins over an override at its reflection position.
   `array_key_exists()` treats explicit `null` as supplied; PHP validates the value when constructing the object.
4. Named non-built-in types become candidate service IDs. This includes class, interface, and enum names.
   `self` (and the resolver's `static` case) uses the requested class; `parent` uses its direct parent, if any.
   For an inherited constructor these are relative to the requested class, not the class declaring the constructor.
5. A union contributes only named non-built-in branches, in reflection order with duplicate IDs removed.
   An intersection contributes no candidates. A DNF type such as `(A&B)|C` considers only `C`; pass an intersection
   value explicitly when needed. Built-in types, including `object`, do not query type-name or parameter-name IDs.
6. All candidate IDs are checked with `has()` in order. Exactly one available candidate is fetched with `get()`.
   Multiple available candidates fail before any `get()`, even if their entries would contain the same object.
   Defaults and nullable types do not resolve ambiguity.
7. If none exists, use the declared default, then `null` if the declared type allows it; otherwise fail.
   Required `mixed` permits `null`; a required parameter without a type does not get this fallback.
8. Extra positional values and unknown named keys are ignored. Only resolved fixed arguments reach the constructor.

For example, constructing `Child::class` with an inherited `self` parameter queries `Child::class`; an inherited
`parent` parameter queries the immediate parent of `Child`. These ID rules do not change PHP's own parameter type checks.
The algorithm's `static` case does not imply that PHP permits `static` as a constructor parameter type.

### Errors

Support throws PHP's `InvalidArgumentException` for unresolved parameters, ambiguous candidates, and unsupported
non-variadic references. Messages identify the class and parameter:

```text
Unable to resolve constructor parameter App\Job::__construct($store) of type App\Store.
Ambiguous constructor parameter App\Job::__construct($store): App\PrimaryStore, App\BackupStore.
Constructor parameter App\Job::__construct($value) cannot be resolved because references are unsupported.
```

The unresolved message omits `of type ...` when the parameter has no declared type. Reflection errors, Context errors,
container `has()` / `get()` errors, and target-constructor errors propagate unchanged. A failed lookup never falls back
to a default, `null`, another candidate, or native construction. Incompatible returned values and invalid targets remain
PHP errors. Callers must supply a constructible class, valid arguments, and a working container when Context is present.
The function-scoped IDE suppression does not catch or suppress runtime exceptions.

## Independent use

Without the `ApplicationContext` class, `make()` uses native `new $className(...$parameters)`, with PHP's own argument
binding rules. It can be loaded by requiring `src/functions.php` alone; no SuperKernel package is required for that path.
The container-specific restrictions above, such as ignoring variadic values and extra keys, do not apply to this path.

With Context installed but no container set, Context governs initialization: installed DI initializes its default
container; without DI it throws `ContainerNotSetException`. For a third-party container, call `setContainer()` first.
Support does not catch Context failures or retry without a container.

## Development

Use a PHP 8.4 Composer in this package's checkout:

```bash
composer install
composer validate --strict
composer test
composer format:check
composer format
```
