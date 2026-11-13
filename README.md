<p align="right">
  <strong>English</strong> | <a href="README.zh-CN.md">中文文档</a>
</p>

# SuperKernel Support

`super-kernel/support` provides the `make()` function, which creates a new object that the caller owns and fills its constructor's dependencies from the application container.

## Installation

```bash
composer require super-kernel/support
```

The package has no requirement beyond PHP. To fill dependencies from a container, install `super-kernel/context`, which supplies the application's container; without it, `make()` constructs with the given arguments only.

## What it provides

- `make($class, $parameters)`, which returns a new instance on every call.
- Constructor arguments by name or position, with the rest filled from the container by type, then from defaults and `null`.
- The same argument rules as `super-kernel/di`, with any PSR-11 container that `super-kernel/context` holds.
- Clear errors for a parameter that cannot be filled, an ambiguous type, and a by-reference parameter.

It does not:

- keep the created object in the container or return a container entry;
- apply `#[Provider]` or `#[FactoryProvider]` mappings, property injection, or lifecycle hooks to the created object;
- create a scope or a new copy of the dependencies: dependencies from the container stay shared.

## Quick start

A service the container provides. Save it as `src/Report/Formatter.php`:

```php
<?php

declare(strict_types=1);

namespace Src\Report;

use function strtoupper;

final class Formatter
{
    public function format(string $format, string $title): string
    {
        return strtoupper($format) . ': ' . $title;
    }
}
```

An object the caller owns, with a dependency and a setting. Save it as `src/Report/Report.php`:

```php
<?php

declare(strict_types=1);

namespace Src\Report;

final readonly class Report
{
    public function __construct(public Formatter $formatter, public string $format = 'text')
    {
    }

    public function render(string $title): string
    {
        return $this->formatter->format($this->format, $title);
    }
}
```

In an application created from `super-kernel/skeleton`, create two reports. Save the script as `bin/report.php`:

```php
<?php

declare(strict_types=1);

use Src\Report\Report;

use function SuperKernel\Support\make;

require dirname(__DIR__) . '/vendor/autoload.php';

$json = make(Report::class, ['format' => 'json']);
$text = make(Report::class);

echo $json->render('Sales'), PHP_EOL;
echo $text->render('Sales'), PHP_EOL;
var_dump($json === $text, $json->formatter === $text->formatter);
```

Build the project and run the script:

```bash
skernel build
php bin/report.php
```

It prints:

```text
JSON: Sales
TEXT: Sales
bool(false)
bool(true)
```

## Public surface

| Name | Kind | Purpose |
| --- | --- | --- |
| `SuperKernel\Support\make` | Helper | Creates a new instance of a class, filling its constructor from the given parameters and the application container. |

## Behavior to know

- `make()` raises `InvalidArgumentException` when a parameter cannot be filled, such as `Unable to resolve constructor parameter Src\Storage\Job::__construct($store) of type Src\Storage\Store.`, when more than one class or interface in a parameter's type has a container entry, and for a by-reference parameter.
- A dependency is found only under the fully qualified name of a class or interface in the parameter's type. A container entry registered under another name, such as `mailer`, is not used.
- Exceptions of the container, of `super-kernel/context`, and of the constructor pass through unchanged. When a parameter cannot be filled, the first failure of the container's `get()` for one of its types other than not found, such as a class that fails to load, is the previous exception of the `InvalidArgumentException`.
- With `super-kernel/context` installed but no container set, `make()` lets the context create the default container from the build metadata, which needs `super-kernel/di` and `skernel build`; set your own container first to use another one.

## Documentation

The developer documentation describes the argument rules, other containers, use without SuperKernel, and the errors with complete examples: [SuperKernel Support](https://github.com/super-kernel/.github/blob/main/docs/components/support.md).

## License

Released under the MIT License. See [LICENSE](LICENSE).
