<?php

namespace Henzeb\Warmable\Tests\Stubs\Illuminate;

use Henzeb\Warmable\Illuminate\Warmable;

class ConstructorInjection extends Warmable
{
    public function __construct(public InjectedService $service)
    {
    }

    public function Warmable(): mixed
    {
        return 'Hello ' . $this->service->value;
    }
}
