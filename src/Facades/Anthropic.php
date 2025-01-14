<?php

namespace Ajz\Anthropic\Facades;

use Illuminate\Support\Facades\Facade;

final class Anthropic extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'anthropic';
    }
}
