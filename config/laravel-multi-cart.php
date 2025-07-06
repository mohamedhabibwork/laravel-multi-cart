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
    | Discount Settings
    |--------------------------------------------------------------------------
    |
    | Configure default discount behavior for all carts.
    |
    */

    'discount' => [
        'enabled' => true,
        'type' => env('LARAVEL_MULTI_CART_DISCOUNT_TYPE', 'percentage'), // 'percentage', 'fixed', or 'tiered'
        'value' => env('LARAVEL_MULTI_CART_DISCOUNT_VALUE', 0.0),
        'included' => env('LARAVEL_MULTI_CART_DISCOUNT_INCLUDED', false), // true if discount is already included in price
        'per_item' => env('LARAVEL_MULTI_CART_DISCOUNT_PER_ITEM', false), // true if discount can be applied per item
        'minimum_amount' => env('LARAVEL_MULTI_CART_DISCOUNT_MIN_AMOUNT', null), // minimum cart amount for discount to apply
        'maximum_amount' => env('LARAVEL_MULTI_CART_DISCOUNT_MAX_AMOUNT', null), // maximum discount amount

        // Tiered discount settings
        'tiers' => [
            // Example: 5% off for 10+ items, 10% off for 20+ items
            // [
            //     'min_quantity' => 10,
            //     'type' => 'percentage',
            //     'value' => 5.0
            // ],
            // [
            //     'min_quantity' => 20,
            //     'type' => 'percentage',
            //     'value' => 10.0
            // ]
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tax Settings
    |--------------------------------------------------------------------------
    |
    | Configure default tax behavior for all carts.
    |
    */

    'tax' => [
        'enabled' => true,
        'type' => env('LARAVEL_MULTI_CART_TAX_TYPE', 'percentage'), // 'percentage' or 'fixed'
        'value' => env('LARAVEL_MULTI_CART_TAX_VALUE', 0.0),
        'included' => env('LARAVEL_MULTI_CART_TAX_INCLUDED', false), // true if tax is already included in price
        'per_item' => env('LARAVEL_MULTI_CART_TAX_PER_ITEM', false), // true if tax can be applied per item
        'compound' => env('LARAVEL_MULTI_CART_TAX_COMPOUND', false), // true if tax is calculated after discount
    ],

    /*
    |--------------------------------------------------------------------------
    | Shipping Settings
    |--------------------------------------------------------------------------
    |
    | Configure default shipping behavior for all carts.
    |
    */

    'shipping' => [
        'enabled' => true,
        'type' => env('LARAVEL_MULTI_CART_SHIPPING_TYPE', 'fixed'), // 'percentage', 'fixed', 'per_piece', or 'weight_based'
        'value' => env('LARAVEL_MULTI_CART_SHIPPING_VALUE', 0.0),
        'included' => env('LARAVEL_MULTI_CART_SHIPPING_INCLUDED', false), // true if shipping is already included in price
        'per_item' => env('LARAVEL_MULTI_CART_SHIPPING_PER_ITEM', false), // true if shipping can be applied per item
        'free_shipping_threshold' => env('LARAVEL_MULTI_CART_FREE_SHIPPING_THRESHOLD', null), // minimum cart amount for free shipping

        // Per-piece shipping settings
        'pieces_per_shipping' => env('LARAVEL_MULTI_CART_PIECES_PER_SHIPPING', 2), // every X pieces gets shipping cost
        'max_shipping_charges' => env('LARAVEL_MULTI_CART_MAX_SHIPPING_CHARGES', null), // null means unlimited charges

        // Weight-based shipping settings
        'base_rate' => env('LARAVEL_MULTI_CART_SHIPPING_BASE_RATE', 0.0), // base shipping cost
        'weight_rate' => env('LARAVEL_MULTI_CART_SHIPPING_WEIGHT_RATE', 0.0), // cost per unit weight
        'free_weight_threshold' => env('LARAVEL_MULTI_CART_FREE_WEIGHT_THRESHOLD', null), // free shipping under this weight
    ],

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
