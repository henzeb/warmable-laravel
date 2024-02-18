<?php

namespace Henzeb\Warmable\Illuminate\Support\Events;

use Illuminate\Queue\SerializesModels;

class CacheWarmedUp
{
    use SerializesModels;

    public function __construct(
        public string $key,
        public string $warmable,
        public mixed  $result
    )
    {
    }
}
