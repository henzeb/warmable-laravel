<?php

namespace Henzeb\Warmable\Illuminate;

use Henzeb\Warmable\CacheItem as BaceCacheItem;
use Illuminate\Queue\SerializesModels;

class CacheItem extends BaceCacheItem
{
    use SerializesModels;
}
