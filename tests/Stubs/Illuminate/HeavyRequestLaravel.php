<?php

namespace Henzeb\Warmable\Tests\Stubs\Illuminate;

use Henzeb\Warmable\Illuminate\Support\HigherOrderWarmableProxy;
use Henzeb\Warmable\Illuminate\Warmable;

class HeavyRequestLaravel extends Warmable
{
    public function warmable(): string
    {
        return 'Hello World';
    }
}
