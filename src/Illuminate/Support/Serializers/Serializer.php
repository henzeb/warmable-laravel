<?php

namespace Henzeb\Warmable\Illuminate\Support\Serializers;

use Illuminate\Queue\SerializesModels;

class Serializer
{
    use SerializesModels;

    public function __construct(protected array $with)
    {
    }
}
