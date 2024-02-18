<?php

namespace Henzeb\Warmable\Tests\Stubs\Illuminate;

use Henzeb\Warmable\Illuminate\Warmable;


class HeavyInjectedRequest extends Warmable
{
    public static ?string $callWarmable = null;

    public function warmable(InjectedService $service, string $text = null): string
    {
        return rtrim('cached: ' . $service->value . ' ' . $text);
    }

    protected function callWarmable(): mixed
    {
        return static::$callWarmable ?? parent::callWarmable();
    }

    public function __destruct()
    {
        static::$callWarmable = null;
    }
}
