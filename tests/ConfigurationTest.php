<?php

use HCart\LaravelMultiCart\Config\LaravelMultiCartConfig;
use HCart\LaravelMultiCart\Facades\LaravelMultiCart;
use HCart\LaravelMultiCart\Models\Cart;
use HCart\LaravelMultiCart\Models\CartItem;
use HCart\LaravelMultiCart\Services\ConfigurationService;
use HCart\LaravelMultiCart\Tests\Fixtures\Product;

beforeEach(function () {
    $this->product = Product::create([
        'name' => 'Test Product',
        'price' => 29.99,
        'sku' => 'TEST-001',
    ]);
});

describe('LaravelMultiCartConfig Class', function () {
    it('can create config with default values', function () {
        $config = new LaravelMultiCartConfig;

        expect($config->getCartModel())->toBe(Cart::class)
            ->and($config->getCartItemModel())->toBe(CartItem::class)
            ->and($config->getTaxRate())->toBe(0.0)
            ->and($config->getCurrency())->toBe('USD')
            ->and($config->getDefaultProvider())->toBe('session')
            ->and($config->shouldPreventDuplicates())->toBeTrue();
    });

    it('can create config with custom values', function () {
        $config = new LaravelMultiCartConfig([
            'models' => [
                'cart' => 'CustomCartModel',
                'cart_item' => 'CustomCartItemModel',
            ],
            'tax_rate' => 0.15,
            'currency' => 'EUR',
            'default_provider' => 'database',
            'prevent_duplicates' => false,
        ]);

        expect($config->getCartModel())->toBe('CustomCartModel')
            ->and($config->getCartItemModel())->toBe('CustomCartItemModel')
            ->and($config->getTaxRate())->toBe(0.15)
            ->and($config->getCurrency())->toBe('EUR')
            ->and($config->getDefaultProvider())->toBe('database')
            ->and($config->shouldPreventDuplicates())->toBeFalse();
    });

    it('can update configuration after creation', function () {
        $config = new LaravelMultiCartConfig;

        expect($config->getCurrency())->toBe('USD');

        $config->setConfig(['currency' => 'GBP']);

        expect($config->getCurrency())->toBe('GBP');
    });

    it('merges configuration when setting new values', function () {
        $config = new LaravelMultiCartConfig([
            'currency' => 'USD',
            'tax_rate' => 0.10,
        ]);

        $config->setConfig(['currency' => 'EUR']);

        expect($config->getCurrency())->toBe('EUR')
            ->and($config->getTaxRate())->toBe(0.10); // Should remain unchanged
    });

    it('can get specific config values', function () {
        $config = new LaravelMultiCartConfig([
            'custom_setting' => 'custom_value',
            'nested' => [
                'setting' => 'nested_value',
            ],
        ]);

        expect($config->getConfig('custom_setting'))->toBe('custom_value')
            ->and($config->getConfig('nested.setting'))->toBe('nested_value')
            ->and($config->getConfig('non_existent'))->toBeNull();
    });

    it('can get entire config array', function () {
        $customConfig = [
            'currency' => 'EUR',
            'tax_rate' => 0.20,
        ];

        $config = new LaravelMultiCartConfig($customConfig);
        $fullConfig = $config->getConfig();

        expect($fullConfig)->toBeArray()
            ->and($fullConfig['currency'])->toBe('EUR')
            ->and($fullConfig['tax_rate'])->toBe(0.20);
    });

    it('provides default unique callback', function () {
        $config = new LaravelMultiCartConfig;
        $callback = $config->getUniqueItemCallback();

        expect($callback)->toBeCallable();

        $uniqueKey = $callback($this->product->id, get_class($this->product), []);
        $expectedKey = md5($this->product->id.get_class($this->product));

        expect($uniqueKey)->toBe($expectedKey);
    });

    it('can use custom unique callback', function () {
        $customCallback = function ($cartableId, $cartableType, $attributes) {
            return 'custom_'.$cartableId.'_'.($attributes['variant'] ?? 'default');
        };

        $config = new LaravelMultiCartConfig([
            'callbacks' => [
                'unique_item' => $customCallback,
            ],
        ]);

        $callback = $config->getUniqueItemCallback();
        expect($callback)->toBe($customCallback);

        $uniqueKey = $callback(123, 'Product', ['variant' => 'large']);
        expect($uniqueKey)->toBe('custom_123_large');
    });
});

describe('Global Configuration Management', function () {
    it('can set global configuration', function () {
        $originalConfig = LaravelMultiCart::getConfig();

        $newConfig = new LaravelMultiCartConfig([
            'currency' => 'JPY',
            'tax_rate' => 0.08,
        ]);

        LaravelMultiCart::setConfig($newConfig);

        $cart = LaravelMultiCart::cart('config_test');
        $cartConfig = $cart->getCartConfig();

        expect($cartConfig->getCurrency())->toBe('JPY')
            ->and($cartConfig->getTaxRate())->toBe(0.08);

        // Restore original config
        LaravelMultiCart::setConfig($originalConfig);
    });

    it('can use custom configuration for specific cart', function () {
        $customConfig = new LaravelMultiCartConfig([
            'currency' => 'CAD',
            'tax_rate' => 0.12,
        ]);

        $cart = LaravelMultiCart::cart('custom_config_cart')->withConfig($customConfig);

        expect($cart->getCartConfig()->getCurrency())->toBe('CAD')
            ->and($cart->getCartConfig()->getTaxRate())->toBe(0.12);
    });

    it('cart-specific configuration overrides global configuration', function () {
        // Set global config
        $globalConfig = new LaravelMultiCartConfig([
            'currency' => 'USD',
            'tax_rate' => 0.10,
        ]);
        LaravelMultiCart::setConfig($globalConfig);

        // Create cart with specific config
        $cartConfig = new LaravelMultiCartConfig([
            'currency' => 'EUR',
            'tax_rate' => 0.20,
        ]);

        $cart = LaravelMultiCart::cart('override_test')->withConfig($cartConfig);

        expect($cart->getCartConfig()->getCurrency())->toBe('EUR')
            ->and($cart->getCartConfig()->getTaxRate())->toBe(0.20);
    });
});

describe('Configuration Service', function () {
    it('can get configuration service from container', function () {
        $configService = app(ConfigurationService::class);

        expect($configService)->toBeInstanceOf(ConfigurationService::class);
    });

    it('configuration service provides default config', function () {
        $configService = app(ConfigurationService::class);
        $config = $configService->getConfig();

        expect($config)->toBeInstanceOf(LaravelMultiCartConfig::class)
            ->and($config->getCartModel())->toBe(Cart::class)
            ->and($config->getCartItemModel())->toBe(CartItem::class);
    });

    it('can update configuration through service', function () {
        $configService = app(ConfigurationService::class);

        $newConfig = new LaravelMultiCartConfig(['currency' => 'AUD']);
        $configService->setConfig($newConfig);

        $retrievedConfig = $configService->getConfig();
        expect($retrievedConfig->getCurrency())->toBe('AUD');
    });
});

describe('Configuration in Cart Operations', function () {
    it('uses tax rate from configuration', function () {
        $config = new LaravelMultiCartConfig(['tax_rate' => 0.15]);
        $cart = LaravelMultiCart::cart('tax_test')->withConfig($config);

        $cart->add($this->product, 1); // $29.99

        expect($cart->subtotal())->toBe(29.99)
            ->and($cart->tax())->toBe(4.4985) // 29.99 * 0.15
            ->and($cart->total())->toBe(34.4885); // 29.99 + 4.4985
    });

    it('uses currency from configuration', function () {
        $config = new LaravelMultiCartConfig(['currency' => 'EUR']);
        $cart = LaravelMultiCart::cart('currency_test')->withConfig($config);

        expect($cart->getCartConfig()->getCurrency())->toBe('EUR');
    });

    it('uses custom models from configuration', function () {
        // Note: In a real test, you'd use actual custom models
        $config = new LaravelMultiCartConfig([
            'models' => [
                'cart' => Cart::class,
                'cart_item' => CartItem::class,
            ],
        ]);

        expect($config->getCartModel())->toBe(Cart::class)
            ->and($config->getCartItemModel())->toBe(CartItem::class);
    });

    it('uses prevent duplicates setting', function () {
        $noDuplicatesConfig = new LaravelMultiCartConfig(['prevent_duplicates' => true]);
        $allowDuplicatesConfig = new LaravelMultiCartConfig(['prevent_duplicates' => false]);

        expect($noDuplicatesConfig->shouldPreventDuplicates())->toBeTrue()
            ->and($allowDuplicatesConfig->shouldPreventDuplicates())->toBeFalse();
    });
});

describe('Configuration Callbacks', function () {
    it('can set and use item update callback', function () {
        $callbackExecuted = false;

        $config = new LaravelMultiCartConfig([
            'callbacks' => [
                'item_update' => function ($cartItem, $oldData, $newData) use (&$callbackExecuted) {
                    $callbackExecuted = true;
                },
            ],
        ]);

        $callback = $config->getItemUpdateCallback();
        expect($callback)->toBeCallable();

        // Simulate callback execution
        $callback(null, ['quantity' => 1], ['quantity' => 2]);
        expect($callbackExecuted)->toBeTrue();
    });

    it('can set and use item remove callback', function () {
        $callbackExecuted = false;

        $config = new LaravelMultiCartConfig([
            'callbacks' => [
                'item_remove' => function ($cartItem) use (&$callbackExecuted) {
                    $callbackExecuted = true;
                },
            ],
        ]);

        $callback = $config->getItemRemoveCallback();
        expect($callback)->toBeCallable();

        // Simulate callback execution
        $callback(null);
        expect($callbackExecuted)->toBeTrue();
    });

    it('returns null for undefined callbacks', function () {
        $config = new LaravelMultiCartConfig;

        expect($config->getItemUpdateCallback())->toBeNull()
            ->and($config->getItemRemoveCallback())->toBeNull();
    });
});

describe('Configuration Persistence', function () {
    it('persists configuration in database provider', function () {
        $config = new LaravelMultiCartConfig([
            'currency' => 'CHF',
            'tax_rate' => 0.077,
        ]);

        $cart = LaravelMultiCart::cart('persist_test', 'database')->withConfig($config);
        $cart->add($this->product);

        // Create new cart instance to test persistence
        $newCart = LaravelMultiCart::cart('persist_test', 'database');

        expect($newCart->getCartConfig()->getCurrency())->toBe('CHF')
            ->and($newCart->getCartConfig()->getTaxRate())->toBe(0.077);
    });

    it('persists configuration in session provider', function () {
        $config = new LaravelMultiCartConfig([
            'currency' => 'SEK',
            'tax_rate' => 0.25,
        ]);

        $cart = LaravelMultiCart::cart('session_persist_test', 'session')->withConfig($config);
        $cart->add($this->product);

        // Create new cart instance to test persistence
        $newCart = LaravelMultiCart::cart('session_persist_test', 'session');

        expect($newCart->getCartConfig()->getCurrency())->toBe('SEK')
            ->and($newCart->getCartConfig()->getTaxRate())->toBe(0.25);
    });
});
