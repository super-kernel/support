<p align="right">
  <a href="README.md">English</a> | <strong>中文文档</strong>
</p>

# SuperKernel Support

`super-kernel/support` 提供带命名空间的便捷函数，除 PHP 外没有其他生产依赖。它可以独立构造对象，也可以通过可选的 Context 集成使用当前的应用容器。

## 调用方持有的构造

```php
use App\Service\Report;

use function SuperKernel\Support\make;

$report = make(Report::class, ['format' => 'json']);
```

每次调用都直接用 `new` 创建一个新的顶层对象。这个实例既不从容器中获取，也不保留在容器中，其生命周期由调用方持有。由容器提供的依赖遵循该容器的生命周期，可能是共享的。不提供瞬态依赖图作用域、属性注入、目标类的 Provider/FactoryProvider 分派、生命周期钩子或子容器。借助容器的调用每次都会重新反射构造函数；Support 不维护反射缓存。

原生签名是 `make(string $className, array $parameters = []): mixed`。其泛型 PHPDoc 返回类型为 `($className is class-string<TClass> ? TClass : mixed)`，对已知的类名字符串保留具体类型。调用成功时返回一个对象；没有表示失败的哨兵值。

## 借助容器的构造

当 `ApplicationContext` 可用时，`make()` 会取得其当前的 PSR 容器。官方 DI 和第三方容器使用同一套参数选择逻辑，只调用 `has()` 和 `get()`，不需要 DI 解析器服务。依赖必须能按其类或接口的完全限定名查询到。只注册为 `mailer` 的服务无法被推断为实现了 `MailerInterface`；请注册接口标识，或显式传入该值。Support 既不枚举服务，也不会自行构造缺失的依赖。

例如，应用创建了一个第三方 PSR 容器，其中有 `App\Store::class` 条目：

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

应用在设置 Context 之前，按该容器实现自己的 API 完成第三方容器的启动。Support 不会附带任何容器实现，也不要求安装 DI 或 Contract。

### 参数选择

Context 可用时，`make()` 沿用 DI 的构造函数参数选择规则：

1. 没有构造函数时得到空参数列表，无论传入了什么覆盖值。
2. 跳过可变参数，包括引用可变参数和显式传入的可变参数值。非可变的引用参数会在读取覆盖值之前失败。
3. 对其余每个参数，按名称的覆盖值优先于其反射位置上的覆盖值。`array_key_exists()` 会把显式的 `null` 视为已传入；构造对象时由 PHP 校验该值。
4. 具名的非内置类型成为候选服务标识，包括类名、接口名和枚举名。`self` 使用所请求的类；`parent` 使用其直接父类（如有）。对于继承来的构造函数，它们相对于所请求的类，而不是声明该构造函数的类。
5. 联合类型只贡献其中具名的非内置分支，按反射顺序排列并去除重复标识。交叉类型不贡献候选。`(A&B)|C` 这样的 DNF 类型只考虑 `C`；需要时请显式传入交叉类型的值。内置类型（包括 `object`）不会查询类型名或参数名标识。
6. 按顺序用 `has()` 检查所有候选标识。恰好有一个可用候选时，用 `get()` 取得它。存在多个可用候选时，会在调用任何 `get()` 之前失败，即使这些条目包含的是同一个对象。默认值和可空类型不能消除歧义。
7. 一个候选都没有时，使用声明的默认值；若声明的类型允许，再使用 `null`；否则失败。必需的 `mixed` 参数允许 `null`；没有类型声明的必需参数不享受这一回退。
8. 多余的位置值和未知的命名键会被忽略。只有解析出的固定参数会传给构造函数。

例如，构造 `Child::class` 时，继承来的 `self` 参数查询 `Child::class`；继承来的 `parent` 参数查询 `Child` 的直接父类。这些标识规则不会改变 PHP 自身的参数类型检查。

### 错误

参数无法解析、候选有歧义，以及不受支持的非可变引用参数，Support 都会抛出 PHP 的 `InvalidArgumentException`。消息会指明类和参数：

```text
Unable to resolve constructor parameter App\Job::__construct($store) of type App\Store.
Ambiguous constructor parameter App\Job::__construct($store): App\PrimaryStore, App\BackupStore.
Constructor parameter App\Job::__construct($value) cannot be resolved because references are unsupported.
```

参数没有类型声明时，无法解析的消息会省略 `of type ...`。反射错误、Context 错误、容器 `has()` / `get()` 的错误以及目标构造函数的错误都会原样传播。查找失败时从不回退到默认值、`null`、其他候选或原生构造。返回值不兼容和目标无效仍是 PHP 错误。Context 存在时，调用方必须提供可构造的类、有效的参数和可用的容器。

## 独立使用

没有 `ApplicationContext` 类时，`make()` 使用原生的 `new $className(...$parameters)`，遵循 PHP 自身的参数绑定规则。单独 require `src/functions.php` 即可加载它；这条路径不需要任何 SuperKernel 包。上面那些容器相关的限制（例如忽略可变参数值和多余的键）不适用于这条路径。

安装了 Context 但未设置容器时，由 Context 负责初始化：安装了 DI 时会初始化其默认容器；没有 DI 时抛出 `ContainerNotSetException`。使用第三方容器时，请先调用 `setContainer()`。Support 不会捕获 Context 的失败，也不会在没有容器的情况下重试。
