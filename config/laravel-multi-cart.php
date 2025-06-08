<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Cart Provider
    |--------------------------------------------------------------------------
    |
    | This value determines the default cart provider that will be used
    | when no provider is explicitly specified. You may change this to
    | any of the providers defined below.
    |
    */

    'default' => env('LARAVEL_MULTI_CART_PROVIDER', 'session'),

    /*
    |--------------------------------------------------------------------------
    | Custom Models
    |--------------------------------------------------------------------------
    |
    | You may specify custom models for carts and cart items. These models
    | must extend the base models provided by this package or implement
    | the appropriate interfaces.
    |
    */

    'models' => [
        'cart' => \HCart\LaravelMultiCart\Models\Cart::class,
        'cart_item' => \HCart\LaravelMultiCart\Models\CartItem::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Configuration Class
    |--------------------------------------------------------------------------
    |
    | The configuration class that implements CartConfigInterface. You can
    | extend the default configuration class to customize behavior.
    |
    */

    'config_class' => \HCart\LaravelMultiCart\Config\LaravelMultiCartConfig::class,

    /*
    |--------------------------------------------------------------------------
    | Cart Behavior Callbacks
    |--------------------------------------------------------------------------
    |
    | These callbacks allow you to customize cart behavior at runtime.
    | Each callback is optional and will use sensible defaults if not provided.
    |
    */

    'callbacks' => [
        // Callback to determine item uniqueness
        // function($cartableId, $cartableType, $attributes) { return 'unique_key'; }
        'unique_item' => null,

        // Callback when item is updated
        // function($cartItem, $oldData, $newData) { /* custom logic */ }
        'item_update' => null,

        // Callback when item is removed
        // function($cartItem) { /* custom logic */ }
        'item_remove' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Cart Behavior Settings
    |--------------------------------------------------------------------------
    |
    | These settings control how carts behave by default.
    |
    */

    'prevent_duplicates' => true,
    'tax_rate' => env('LARAVEL_MULTI_CART_TAX_RATE', 0.0),
    'currency' => env('LARAVEL_MULTI_CART_CURRENCY', 'USD'),

    /*
    |--------------------------------------------------------------------------
    | Cart Providers
    |--------------------------------------------------------------------------
    |
    | Here you may configure the cart providers for your application. Each
    | provider has its own configuration options and storage mechanisms.
    |
    */

    'providers' => [
        'session' => [
            'driver' => 'session',
            'prefix' => 'laravel_multi_cart_',
        ],

        'cache' => [
            'driver' => 'cache',
            'store' => env('CACHE_DRIVER', 'file'),
            'prefix' => 'laravel_multi_cart_',
            'ttl' => 3600,
        ],

        'database' => [
            'driver' => 'database',
            'connection' => env('DB_CONNECTION', 'mysql'),
            'soft_deletes' => true,
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => env('REDIS_CONNECTION', 'default'),
            'prefix' => 'laravel_multi_cart_',
            'ttl' => 3600,
        ],

        'file' => [
            'driver' => 'file',
            'path' => storage_path('laravel_multi_cart'),
            'ttl' => 3600,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cart Cleanup Configuration
    |--------------------------------------------------------------------------
    |
    | Configure automatic cleanup of expired carts. This helps maintain
    | optimal performance by removing old cart data.
    |
    */

    'cleanup' => [
        'enabled' => true,
        'schedule' => 'daily',
        'expire_after' => 168, // hours (7 days)
    ],
];
