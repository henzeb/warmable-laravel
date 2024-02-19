<?php

namespace Henzeb\Warmable\Illuminate;

use Arr;
use Henzeb\Warmable\Illuminate\Support\Events\CacheFailedWarmingUp;
use Henzeb\Warmable\Illuminate\Support\Events\CachePreheating;
use Henzeb\Warmable\Illuminate\Support\Events\CacheWarmedUp;
use Henzeb\Warmable\Illuminate\Support\Events\CacheWarmingUp;
use Henzeb\Warmable\Illuminate\Support\RepositoryWrapper;
use Henzeb\Warmable\Illuminate\Support\Serializers\Serializer;
use Henzeb\Warmable\Warmable as BaseWarmable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Queue\Queue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\PendingDispatch;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Routing\ResolvesRouteDependencies;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue as QueueFacade;
use Illuminate\Support\Traits\Conditionable;
use Psr\SimpleCache\CacheInterface;

/**
 * @method static PendingDispatch preheat()
 * @method PendingDispatch preheat()
 * @method $this withPreheating(bool $useQueue = false)
 * @method static $this withoutPreheating(bool $useQueue = false)
 * @method static $this make(array $parameters = null)
 * @method static $this withCache(?CacheInterface|string $cache)
 * @method $this withCache(?CacheInterface|string $cache)
 * @mixin BaseWarmable
 */
abstract class Warmable extends BaseWarmable implements ShouldQueue, ShouldBeUnique
{
    use Queueable, InteractsWithQueue, SerializesModels, ResolvesRouteDependencies, Conditionable;

    protected string $preheatingEvent = CachePreheating::class;
    protected string $warmingUpEvent = CacheWarmingUp::class;
    protected string $failedWarmingUpEvent = CacheFailedWarmingUp::class;
    protected string $warmedUpEvent = CacheWarmedUp::class;

    private ?Application $container = null;

    protected function cache(): CacheInterface
    {
        return Cache::driver();
    }

    public function uniqueId(): string
    {
        return static::class . '.' . $this->getKey();
    }

    public function __invoke(): bool
    {
        return $this->warmup();
    }

    public function warmup(): bool
    {
        event(
            resolve(
                $this->warmingUpEvent,
                [
                    'key' => $this->callGetKey(),
                    'warmable' => $this::class,
                ]
            )
        );

        parent::warmup();

        if (false === $this->isPreheated()) {
            event(
                resolve(
                    $this->failedWarmingUpEvent,
                    [
                        'key' => $this->callGetKey(),
                        'warmable' => $this,
                    ]
                )
            );

            return false;
        }

        event(
            resolve(
                $this->warmedUpEvent,
                [
                    'key' => $this->callGetKey(),
                    'warmable' => $this::class,
                    'result' => $this->callGet()
                ]
            )
        );

        return $this->isPreheated();
    }

    protected function wrapInCacheItem(int $ttl, mixed $data): CacheItem
    {
        return new CacheItem($ttl, $data);
    }

    protected function getPreheated(bool $hasDefault): mixed
    {
        if (
            QueueFacade::getName($this->connection) !== 'sync'
            || App::runningUnitTests()
        ) {
            $this->callPreheat();

            return null;
        }

        event(
            resolve(
                $this->preheatingEvent,
                [
                    'key' => $this->callGetKey(),
                    'warmable' => $this::class,
                ]
            )
        );

        return parent::getPreheated($hasDefault);
    }

    protected function afterShutdown(callable $afterShutdown): void
    {
        App::terminating($afterShutdown);
    }

    protected function callPreheat(): PendingDispatch
    {
        event(
            resolve(
                $this->preheatingEvent,
                [
                    'key' => $this->callGetKey(),
                    'warmable' => $this::class,
                ]
            )
        );

        return dispatch(
            $this
        );
    }

    protected function callWith(mixed ...$values): static
    {
        if (count($values) === 1 && is_array($values[0]) && Arr::isAssoc($values[0])) {
            $this->with = $values[0];
            return $this;
        }

        return parent::callWith(...$values);
    }

    protected function calculateHash(array $with): string
    {
        return sha1(
            serialize(
                new Serializer($with)
            )
        );
    }

    private static function getCacheRepository(
        CacheInterface|string|null $store
    ): ?CacheInterface
    {
        if ($store instanceof Repository) {
            return new RepositoryWrapper($store);
        }

        if ($store instanceof CacheInterface) {
            return $store;
        }

        if (is_string($store)) {
            return new RepositoryWrapper(
                Cache::driver($store),
                $store
            );
        }

        return null;
    }

    protected function callWithCache(
        CacheInterface|string|null $cache
    ): static
    {
        $this->cache = self::getCacheRepository($cache);

        return $this;
    }

    protected function callWithPreheating(bool $useQueue = false): static
    {
        if ($useQueue) {

            if ($this->callMissing()) {
                $this->callPreheat();
                $this->preheated = true;
            }

            return $this;
        }

        return parent::callWithPreheating();
    }

    protected function getCache(): CacheInterface
    {
        return self::getCacheRepository($this->cache ?? $this->cache());
    }

    protected function executeWarmable(): mixed
    {
        $this->container = app();

        return $this->warmable(
            ...
            $this->resolveClassMethodDependencies(
                $this->with,
                $this,
                'warmable'
            )
        );
    }

    protected static function resolveNewInstance(): static
    {
        $parameters = array_merge(...func_get_args());

        return resolve(static::class, $parameters);
    }

    public function queue(
        Queue    $queue,
        Warmable $command
    ): void
    {
        if ($command->isPreheated()) {
            return;
        }

        $queue->push($command);
    }
}
