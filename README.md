# Warmable for Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/henzeb/warmable-laravel.svg?style=flat-square)](https://packagist.org/packages/henzeb/warmable-laravel)
[![Total Downloads](https://img.shields.io/packagist/dt/henzeb/warmable-laravel.svg?style=flat-square)](https://packagist.org/packages/henzeb/warmable-laravel)

This package was inspired by a talk on LaraconEU 2024 by [@timacdonald](https://github.com/timacdonald)
where he showed a technique to warm up a cache entry scheduled by
using a simple invokable class.

When you cache the result of a heavy operation, once in a while, some user
will have to wait for the operation to be cached again. If the request rate is
high enough, multiple people may have to do so at the same time.

This package aims to take away this situation as much as possible,
by utilizing both the Scheduler and the Queue.

It's build with Laravel in mind, but as I used the PSR-16 `psr/simple-cache`
CacheInterface, anyone can use it. Just install the vanilla
[Warmable](https://packagist.org/packages/henzeb/warmable), as this package is Laravel Specific

## Installation

Just install with the following command.

```bash
composer require henzeb/warmable-laravel
```

## Terminology

#### Warm up / warming up

The term warm up or warming up refers to caching
during a cron or at the queue.

#### preheating

Preheating is the term used when the cache is populated during
execution, which is the default operation when the cache does not exist.
This may also be dispatched to a background operation like
a Queueing System.

## Usage

The explanation here is specific to laravel. For more information,
read the docs at [Warmable](https://packagist.org/packages/henzeb/warmable).

### Creating a Warmable

Creating a `Warmable` is pretty easy. As you can see, no `cache` method is
required, as out of the box, `Warmable` is using the default `Cache` driver.

You still can override it and use a different driver if you choose so. You
don't have to stick with a `CacheInterface` implementation, you can use
strings as well. Anywhere you can input the cache driver, you can use strings.

Note: In fact, using a string is performance wise better, because on
serialization for the Queue, it needs to loop through all your configured
stores to determine the name it came with.

```php
use Henzeb\Warmable\Illuminate\Warmable;
use DateTimeInterface;
use DateInterval;

class HeavyOperation extends Warmable
{
    protected function warmable(): mixed 
    {
         // ... your heavy operation
    }
}
```

### Dependency Injection

`Warmable Laravel` utilizes the Dependency Injection system from laravel. It
works similar to how dependencies are resolved on a `Controller`, as it uses
the same implementation.

```php
use Henzeb\Warmable\Illuminate\Warmable;
use DateTimeInterface;
use DateInterval;

class HeavyOperation extends Warmable
{    
    protected function warmable(YourInjectedService $service): mixed 
    {
         // ... your heavy operation
    }
}
```

Dependencies can still be injected with the `with` method, just like the vanilla
`Warmable` implementation, with the addition that you can use an associated array
to inject custom variables.

Below example will inject the instance of `YourInjectedService`, rather than resolving
a new instance using the Application Container.

````php
HeavyOperation::with([
    'service' => new YourInjectedService()
]);

// The following however isn't possible:
HeavyOperation::with(
    [
        'service' => new YourInjectedService()
    ], 
    'another item'
);
````

Note: Be aware that services injected using `with` are changing the key, making it
unique. See [Warmable](https://packagist.org/packages/henzeb/warmable) for more information.

#### injecting in the constructor

You can also inject dependencies in the constructor, just like as you are used to. You
don't have to resolve the instance using `resolve`, because `Warmable` does that for you
under the hood using `make`.

```php
use Henzeb\Warmable\Illuminate\Warmable;

class HeavyOperation extends Warmable {
    public function __construct(
    private YourService $yourService
    private YourOtherService $otherService
    ) {
    }
}

HeavyOperation::make();

HeavyOperation::make(['yourService' => new YourService()]);
```

### get

The get method behaves just like its vanilla counterpart, except that when the
cache item does not exist yet, it will dispatch itself as a job to the queue,
And then immediately returns whatever is set as default. As you are used to, it
accepts callables as default value.

Note: If the `connection` variable is set to `sync`, it will create a cache right away
and returns its result. When a default is set, it will do that after returning it's
response to the browser.

### Scheduling & Queueing

A `Warmable` is out of the box a `Job` implementing the `ShouldQueue` interface.
This makes it very easy to schedule the `Warmable` and dispatch it to the queue.

The `Warmable` is invokable, allowing you to just throw in the FQCN and
never look back.

```php
$schedule->call(HeavyOperation::class)->daily(); // happens during execution
$schedule->job(HeavyOperation::class)->hourly(); // dispatches to the queue
```

Note: It also implements the `ShouldBeUnique` interface, this
makes sure It won't run it twice at the same time. As `Warmable` is just a job,
you can configure this further. See the Laravel documentation for more information.

#### Ensuring the cache is warmed up

Both will warm up the cache at the scheduled time. But if you need to make sure
That the cache is warmed up as soon as the cache is expired or gone, you can utilize
`withPreheating`, which will, depending on your configuration, dispatch a job or call
immediately, even if the specified interval hasn't been met.

```php
$schedule->call(HeavyOperation::withPreheating())->daily();

// Will dispatch a job on the queue when preheating needs to happen
$schedule->job(HeavyOperation::withPreheating(true))->hourly();
```

Note: The beauty in all this is that whenever a `Warmable` has preheated,
it won't queue or execute anything. So it won't do the preheating
and then do it again.

### preheat

A special method that is very useful when data changes sometimes and the
cache needs to reflect those changes as soon as possible. It dispatches the
`Warmable` as a job to the queue. This allows the user to move on and not
having to wait for the save operation to be completed.

The method returns an instance of `PendingDispatch`, allowing you to modify
configuration before actually getting dispatched.

```php
HeavyOperation::preheat()->onQueue('sync');
```

### Events

In total, `Warmable` emits 4 different events. The names should be
self-explanatory about when they are emitted.

| event                                                          |
|----------------------------------------------------------------|
| Henzeb\Warmable\Illuminate\Support\Events\CachePreheating      |
| Henzeb\Warmable\Illuminate\Support\Events\CacheWarmingUp       |
| Henzeb\Warmable\Illuminate\Support\Events\CacheFailedWarmingUp | 
| Henzeb\Warmable\Illuminate\Support\Events\CacheWarmedUp        |

Each event receives the `key` and the `warmable` as FQCN. Except for
`CacheFailedWarmingUp`, which receives the actual instance for debugging
purposes.

`CacheWarmedUp` also receives the `result`. That's what in the cache.

#### Using custom events

Each event can be changed by using instance variables. The custom events are
emitted by this `Warmable` only. This allows you to only have to listen
to events of that particular `Warmable`and not having to implement
multiple `if` or `match` statements inside a listener.

````php
use Henzeb\Warmable\Illuminate\Warmable;

class YourEvent {
    public function __construct(
        public string $key, // not required, the key of your Warmable
        public string $warmable // not required, FQCN of your Warmable
        public mixed $result = null // not required, only for CacheWarmedUp events
    ) {
    }
} 

class HeavyOperation extends Warmable {
    protected string $preheatingEvent = YourEvent::class;
    
    protected string $warmingUpEvent = YourEvent::class;
    
    protected string $failedWarmingUpEvent = YourEvent::class;
    
    protected string $warmedUpEvent = YourEvent::class;
} 
````

## Testing

```bash
composer test
```

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

### Security

If you discover any security related issues, please email henzeberkheij@gmail.com instead of using the issue tracker.

## Credits

- [Henze Berkheij](https://github.com/henzeb)

## License

The GNU AGPLv. Please see [License File](LICENSE.md) for more information.
