<?php

namespace HCart\LaravelMultiCart\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \HCart\LaravelMultiCart\LaravelMultiCart
 */
class LaravelMultiCart extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \HCart\LaravelMultiCart\LaravelMultiCart::class;
    }
}
