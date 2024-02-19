<?php

namespace Henzeb\Warmable\Tests\Unit\Illuminate;


use Event;
use Henzeb\DateTime\DateTime;
use Henzeb\Warmable\Illuminate\CacheItem;
use Henzeb\Warmable\Illuminate\Support\Events\CacheFailedWarmingUp;
use Henzeb\Warmable\Illuminate\Support\Events\CachePreheating;
use Henzeb\Warmable\Illuminate\Support\Events\CacheWarmedUp;
use Henzeb\Warmable\Illuminate\Support\Events\CacheWarmingUp;
use Henzeb\Warmable\Tests\Stubs\Illuminate\HeavyInjectedRequest;
use Henzeb\Warmable\Tests\Stubs\Illuminate\HeavyRequestLaravel;
use Henzeb\Warmable\Tests\Stubs\Illuminate\InjectedService;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Orchestra\Testbench\TestCase;
use TypeError;

class WarmableTest extends TestCase
{
    const CACHE_KEY = 'warmable.' . HeavyRequestLaravel::class;

    public function testUsingScheduler(): void
    {
        Queue::fake();

        $this->app->make(Schedule::class)->call(
            HeavyRequestLaravel::class
        );

        $this->app->make(Schedule::class)->events()[0]->run($this->app);

        Queue::assertNothingPushed();
    }

    public function testDispatchToQueueUsingScheduler(): void
    {
        Queue::fake();

        $this->app->make(Schedule::class)->job(HeavyRequestLaravel::class)->everyMinute();

        $this->app->make(Schedule::class)->events()[0]->run($this->app);

        Queue::assertPushed(HeavyRequestLaravel::class);
    }

    public function testShouldntQueueWhenPreheated(): void
    {
        Queue::fake();

        $this->app->make(Schedule::class)->job(HeavyRequestLaravel::withPreheating())->everyMinute();

        $this->app->make(Schedule::class)->events()[0]->run($this->app);

        Queue::assertNotPushed(HeavyRequestLaravel::class);
    }

    public function testWithPreheatingShouldDispatchByQueue(): void
    {
        Queue::fake();

        $warmable = HeavyRequestLaravel::withPreheating(true);

        $this->assertTrue($warmable->isPreheated());

        Queue::assertPushed(HeavyRequestLaravel::class);
    }

    public function testWithPreheatingShouldNotDispatchByQueue(): void
    {
        Queue::fake();

        $warmable = HeavyRequestLaravel::withPreheating();

        $this->assertTrue($warmable->isPreheated());

        Queue::assertNotPushed(HeavyRequestLaravel::class);
    }

    public function testGetShouldDispatchPreheatOnGet(): void
    {
        Queue::fake();

        $this->assertNull(
            HeavyRequestLaravel::get(),
        );

        Queue::assertPushed(HeavyRequestLaravel::class);

        $this->assertEquals(
            null,
            Cache::get(self::CACHE_KEY)
        );
    }

    public function testShouldDispatchEvent(): void
    {
        Event::fake();

        HeavyRequestLaravel::get();

        Event::assertDispatched(CachePreheating::class);

        Event::assertNotDispatched(CacheFailedWarmingUp::class);

        Event::assertDispatched(
            CacheWarmingUp::class,
            fn(CacheWarmingUp $event) => $event->key === self::CACHE_KEY
                && $event->warmable === HeavyRequestLaravel::class
        );
        Event::assertDispatched(
            CacheWarmedUp::class,
            fn(CacheWarmedUp $event) => $event->key === self::CACHE_KEY
                && $event->warmable === HeavyRequestLaravel::class
                && $event->result === 'Hello World'
        );
    }

    public function testShouldDispatchPreheatEvent(): void
    {
        Event::fake();

        Queue::fake();

        HeavyRequestLaravel::preheat();

        Event::assertDispatched(
            CachePreheating::class,
            fn(CachePreheating $event) => $event->key === self::CACHE_KEY
                && $event->warmable === HeavyRequestLaravel::class
        );

        Event::assertDispatchedTimes(CachePreheating::class, 1);

        Event::assertNotDispatched(CacheWarmingUp::class);
        Event::assertNotDispatched(CacheWarmedUp::class);
    }

    public function testShouldDispatchEventWhenNotUsingQueue(): void
    {
        Event::fake();

        App::expects('runningUnitTests')->andReturn(false);

        HeavyRequestLaravel::get();

        Event::assertDispatched(CachePreheating::class);
    }

    public function testShouldDispatchFailedWarmingUpEvent(): void
    {
        Event::fake();

        $cache = Mockery::mock(Repository::class);

        $cache->expects('put')->andReturnFalse()->once();

        Cache::extend('test', fn() => $cache);
        Config::set('cache.stores.test', ['driver' => 'test']);

        $warmable = HeavyRequestLaravel::withCache('test');

        $this->assertFalse($warmable->warmup());

        Event::assertDispatched(
            CacheFailedWarmingUp::class,
            fn(CacheFailedWarmingUp $event) => $event->key === self::CACHE_KEY
                && $event->warmable instanceof HeavyRequestLaravel
        );

        Event::assertNotDispatched(CacheWarmedUp::class);
    }

    public function testGetShouldUseDefaultAndDispatchPreheat(): void
    {
        Queue::fake();

        $this->assertEquals(
            'Hello Space',
            HeavyRequestLaravel::get('Hello Space'),
        );

        Queue::assertPushed(HeavyRequestLaravel::class);

        $this->assertEquals(
            null,
            Cache::get(self::CACHE_KEY)
        );
    }

    public function testPreheatShouldQueue(): void
    {
        Queue::fake();

        HeavyRequestLaravel::preheat()->delay(10);

        Queue::assertPushed(HeavyRequestLaravel::class, function (HeavyRequestLaravel $job) {
            return $job->delay === 10;
        });
    }

    public function testShouldNotQueueOnSync(): void
    {
        Queue::fake();

        Queue::partialMock()->shouldReceive('getName')
            ->with(null)
            ->andReturn('sync');

        App::partialMock()->expects('runningUnitTests')->andReturnFalse();

        HeavyRequestLaravel::get();

        Queue::assertNotPushed(HeavyRequestLaravel::class);
    }

    public function testShouldUseDefaultBehaviourWhenOnSyncConnection(): void
    {
        Queue::setDefaultDriver('redis');

        $warmable = HeavyRequestLaravel::make();

        $warmable->connection = 'sync';

        $this->assertEquals(null, $warmable->get());

        App::terminate();

        $this->assertEquals('Hello World', $warmable->get());
    }

    public function testWithCacheUsingString()
    {
        Cache::extend('myStore', fn() => new Repository(new ArrayStore()));

        Config::set('cache.stores.myStore', ['driver' => 'array']);

        $implementation = HeavyRequestLaravel::withKey('test')
            ->withCache('myStore')
            ->withPreheating();

        serialize($implementation);

        $this->assertEquals(
            'Hello World',
            Cache::driver('myStore')
                ->get('test')
        );
    }

    public function testWithCacheUsingRepository()
    {
        Cache::extend('myStore', fn() => new Repository(new ArrayStore()));

        Config::set('cache.stores.myStore', ['driver' => 'array']);

        $implementation = HeavyRequestLaravel::withKey('test')
            ->withCache(Cache::driver('myStore'))
            ->withPreheating();

        serialize($implementation);

        $this->assertEquals(
            'Hello World',
            Cache::driver('myStore')
                ->get('test')
        );
    }

    public function testDependencyInjection(): void
    {
        $this->app->bind(InjectedService::class, fn() => tap(new InjectedService(), fn($service) => $service->value = 'injected'));

        $this->assertEquals('cached: injected', HeavyInjectedRequest::withPreheating()->get());

        Cache::clear();

        $this->assertEquals('cached: injected test',
            HeavyInjectedRequest::with('test')
                ->withPreheating()
                ->get()
        );

        Cache::clear();

        $this->assertEquals('cached: injected with test', HeavyInjectedRequest::with(
            [
                'text' => 'test',
                'service' => tap(new InjectedService(), fn($service) => $service->value = 'injected with'),
            ]
        )->withPreheating()->get());

        Cache::clear();

        $this->expectException(TypeError::class);

        $this->assertEquals('cached: injected', HeavyInjectedRequest::with(['test'])->withPreheating()->get());
    }

    public function testUniqueId()
    {
        $this->assertEquals(
            HeavyRequestLaravel::class . '.warmable.' . HeavyRequestLaravel::class . '.5a658a8ee0d8e388b8dc1be8e72c61a105e76a99',
            HeavyRequestLaravel::with('test')->uniqueId()
        );
    }

    public function testShouldUseIlluminateCacheItem(): void
    {
        DateTime::setTestNow('2024-01-01 00:00:00');
        HeavyRequestLaravel::withKey('test')
            ->withTtl(10)
            ->withGracePeriod(10)
            ->warmup();

        $cacheItem = Cache::get('test');

        $this->assertInstanceOf(CacheItem::class, $cacheItem);

        $this->assertEquals(1704067210, $cacheItem->ttl);
        $this->assertEquals('Hello World', $cacheItem->data);
    }

    public function testafterShutdown(): void
    {
        $mock = App::spy();
        $mock->shouldReceive('terminating')->withArgs(
            function (callable $callable) {
                $callable();
                return true;
            }
        )->once();

        $this->assertEquals('default', HeavyRequestLaravel::get('default'));

        $this->assertEquals('Hello World', HeavyRequestLaravel::get('default'));
    }
}
