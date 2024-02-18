<?php

namespace Henzeb\Warmable\Illuminate\Support\Events;

use Henzeb\Warmable\Illuminate\Warmable;

class CacheFailedWarmingUp
{
    public function __construct(
        public string   $key,
        public Warmable $warmable
    )
    {

    }
}
