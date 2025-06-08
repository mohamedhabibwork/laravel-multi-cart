<?php

namespace HCart\LaravelMultiCart\Facades;

use HCart\LaravelMultiCart\Contracts\CartConfigInterface;
use HCart\LaravelMultiCart\Services\CartManager;
use HCart\LaravelMultiCart\Services\CartService;
use Illuminate\Support\Facades\Facade;

/**
 * @method static CartService cart(string $name, string $provider = null)
 * @method static CartService create(string $name, array $config = [], string $provider = null)
 * @method static CartService createStrict(string $name, array $config = [], string $provider = null)
 * @method static bool delete(string $name, string $provider = null)
 * @method static bool exists(string $name, string $provider = null)
 * @method static array getAllCartNames(string $provider = null)
 * @method static void setConfig(CartConfigInterface $config)
 * @method static CartConfigInterface getConfig()
 * @method static bool flush(string $provider = null)
 * @method static CartManager getManager()
 *                                         Cart Service Methods (when called on cart instance):
 * @method CartService clone(string $newCartName, string $provider = null) Clone cart to new name
 * @method CartService convertToProvider(string $newProvider) Convert cart to different provider
 * @method bool exists() Check if cart exists
 * @method string getName() Get cart name
 * @method string getProvider() Get cart provider
 *
 * @mixin \HCart\LaravelMultiCart\LaravelMultiCart
 *
 * @see \HCart\LaravelMultiCart\LaravelMultiCart
 */
class LaravelMultiCart extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'laravel-multi-cart';
    }
}
