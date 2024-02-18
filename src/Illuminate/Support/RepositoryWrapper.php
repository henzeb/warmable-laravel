<?php

namespace Henzeb\Warmable\Illuminate\Support;

use DateInterval;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Psr\SimpleCache\CacheInterface;
use RuntimeException;
use Throwable;

class RepositoryWrapper implements CacheInterface
{
    public function __construct(
        private readonly Repository $driver,
        private readonly ?string    $driverName = null
    )
    {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->driver->get($key);
    }

    public function set(
        string                $key,
        mixed                 $value,
        DateInterval|int|null $ttl = null
    ): bool
    {
        return $this->driver->put($key, $value, $ttl);
    }

    public function delete(string $key): bool
    {
        return $this->driver->forget($key);
    }

    public function clear(): bool
    {
        // TODO: Implement clear() method.
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        // TODO: Implement getMultiple() method.
    }

    public function setMultiple(iterable $values, DateInterval|int|null $ttl = null): bool
    {
        // TODO: Implement setMultiple() method.
    }

    public function deleteMultiple(iterable $keys): bool
    {
        // TODO: Implement deleteMultiple() method.
    }

    public function has(string $key): bool
    {
        return $this->driver->get($key) !== null;
    }


    public function __serialize(): array
    {
        return [
            'driver' => $this->determineDriverName()
        ];
    }

    public function __unserialize(array $data): void
    {
        $this->driverName = $data['driver'];
        $this->driver = Cache::driver($data['driver']);
    }

    private function determineDriverName(): string
    {
        if ($this->driverName) {
            return $this->driverName;
        }

        return collect(
            Config::get('cache.stores')
        )->keys()
            ->filter(
                function ($key) {
                    try {
                        return Cache::store($key) === $this->driver;
                    } catch (Throwable) {
                        return false;
                    }
                }
            )->last() ?? throw new RuntimeException(
                'Cannot determine driver name. Verify that the config for your cache driver still exists',
        );
    }
}
