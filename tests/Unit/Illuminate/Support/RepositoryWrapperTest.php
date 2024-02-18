<?php

namespace Henzeb\Warmable\Tests\Unit\Illuminate\Support;

use Arr;
use Config;
use Exception;
use Henzeb\Warmable\Illuminate\Support\RepositoryWrapper;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Orchestra\Testbench\TestCase;
use RuntimeException;


class RepositoryWrapperTest extends TestCase
{
    public function testSerializingRepository(): void
    {
        $serialized = serialize(new RepositoryWrapper(Cache::store('array')));

        $repository = unserialize($serialized);

        $repository->set('test', 'Hello World');

        $this->assertSame('O:52:"Henzeb\Warmable\Illuminate\Support\RepositoryWrapper":1:{s:6:"driver";s:5:"array";}', $serialized);

        $this->assertSame('Hello World', Cache::store('array')->get('test'));
    }

    public function testSerializingRepositoryWithKnownName(): void
    {
        Cache::extend('myStore', fn() => new Repository(new ArrayStore()));
        Config::set('cache.stores.myStore', ['driver' => 'array']);

        $serialized = serialize(new RepositoryWrapper(Cache::store('array'), 'myStore'));

        $repository = unserialize($serialized);

        $repository->set('test', 'Hello World');

        $this->assertSame('O:52:"Henzeb\Warmable\Illuminate\Support\RepositoryWrapper":1:{s:6:"driver";s:7:"myStore";}', $serialized);

        $this->assertSame('Hello World', Cache::store('myStore')->get('test'));

        $this->assertNull(Cache::store('array')->get('test'));
    }

    public function testShouldNeverReturnNullWhenTryingToDetermineName() {
        $array = Cache::driver('array');
        $config = Cache::get('cache.stores');
        unset($config['array']);
        Config::set('cache.stores', $config);

        $this->expectException(RuntimeException::class);

        serialize(new RepositoryWrapper($array));
    }

    public function testDelete(): void {
        Cache::set('test', 'Hello World');

        (new RepositoryWrapper(Cache::driver()))->delete('test');

        $this->assertNull(Cache::get('test'));
    }
}
