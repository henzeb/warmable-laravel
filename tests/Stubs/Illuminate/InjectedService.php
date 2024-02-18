<?php

namespace Henzeb\Warmable\Tests\Stubs\Illuminate;

class InjectedService
{
    public function __construct(public ?string $value = null)
    {
    }
}
