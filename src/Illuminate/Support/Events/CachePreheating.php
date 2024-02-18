<?php

namespace Henzeb\Warmable\Illuminate\Support\Events;

class CachePreheating
{
    public function __construct(
        public string $key,
        public string $warmable
    )
    {
    }
}
