<p align="right">
  <a href="README.md">English</a> | <strong>中文文档</strong>
</p>

# SuperKernel Support

`super-kernel/support` 提供 `make()` 函数，它创建一个由调用方持有的新对象，并从应用容器中填充其构造函数的依赖。

## 安装

```bash
composer require super-kernel/support
```

本包除 PHP 外没有其他要求。要从容器填充依赖，请安装提供应用容器的 `super-kernel/context`；没有它时，`make()` 只用给定的参数进行构造。

## 提供的能力

- `make($class, $parameters)`，每次调用都返回一个新实例。
- 按名称或位置给出的构造函数参数，其余参数按类型从容器填充，然后取默认值和 `null`。
- 与 `super-kernel/di` 相同的参数规则，适用于 `super-kernel/context` 持有的任何 PSR-11 容器。
- 对无法填充的参数、有歧义的类型以及引用传递的参数给出明确的错误。

它不会：

- 把创建的对象保存在容器中，或者返回容器条目；
- 对创建的对象应用 `#[Provider]` 或 `#[FactoryProvider]` 映射、属性注入或生命周期钩子；
- 创建作用域或依赖的新副本：来自容器的依赖仍然是共享的。

## 快速开始

一个由容器提供的服务。将它保存为 `src/Report/Formatter.php`：

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

一个由调用方持有的对象，带有一个依赖和一个设置。将它保存为 `src/Report/Report.php`：

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

在由 `super-kernel/skeleton` 创建的应用中，创建两份报告。将脚本保存为 `bin/report.php`：

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

构建项目并运行脚本：

```bash
skernel build
php bin/report.php
```

它会打印：

```text
JSON: Sales
TEXT: Sales
bool(false)
bool(true)
```

## 公开接口

| 名称 | 类别 | 用途 |
| --- | --- | --- |
| `SuperKernel\Support\make` | 辅助函数 | 创建一个类的新实例，用给定的参数和应用容器填充其构造函数。 |

## 需要了解的行为

- 当某个参数无法填充时（例如 `Unable to resolve constructor parameter Src\Storage\Job::__construct($store) of type Src\Storage\Store.`）、当参数类型中有多个类或接口在容器中存在条目时，以及对于引用传递的参数，`make()` 抛出 `InvalidArgumentException`。
- 依赖只按参数类型中某个类或接口的完全限定名查找。以其他名称（例如 `mailer`）注册的容器条目不会被使用。
- 容器、`super-kernel/context` 和构造函数抛出的异常会原样传出。
- 安装了 `super-kernel/context` 但没有设置容器时，`make()` 让上下文从构建元数据创建默认容器，这需要 `super-kernel/di` 和 `skernel build`；要使用其他容器，请先设置你自己的容器。

## 文档

开发者文档用完整示例介绍参数规则、其他容器、不依赖 SuperKernel 的用法以及各种错误：[SuperKernel Support](https://github.com/super-kernel/.github/blob/main/docs/components/support.zh-CN.md)。

## 许可证

基于 MIT 许可证发布，详见 [LICENSE](LICENSE)。
