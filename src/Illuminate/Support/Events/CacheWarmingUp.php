<?php

namespace Henzeb\Warmable\Illuminate\Support\Events;

class CacheWarmingUp
{
    public function __construct(
        public string $key,
        public string $warmable
    )
    {
    }
}
