<?php

use HCart\LaravelMultiCart\Enums\CartProvider;
use HCart\LaravelMultiCart\Facades\LaravelMultiCart;
use HCart\LaravelMultiCart\Providers\CacheCartProvider;
use HCart\LaravelMultiCart\Providers\DatabaseCartProvider;
use HCart\LaravelMultiCart\Providers\FileCartProvider;
use HCart\LaravelMultiCart\Providers\SessionCartProvider;
use HCart\LaravelMultiCart\Services\CartManager;
use HCart\LaravelMultiCart\Tests\Fixtures\Product;

beforeEach(function () {
    $this->product = Product::create([
        'name' => 'Test Product',
        'price' => 29.99,
        'sku' => 'TEST-001',
    ]);
});

describe('Session Provider', function () {
    it('can create session cart provider', function () {
        $manager = app(CartManager::class);
        $provider = $manager->getProvider(CartProvider::SESSION->value);

        expect($provider)->toBeInstanceOf(SessionCartProvider::class);
    });

    it('can store and retrieve cart data in session', function () {
        $cart = LaravelMultiCart::cart('session_test', CartProvider::SESSION->value);
        $cart->add($this->product, 2);

        expect($cart->count())->toBe(2)
            ->and($cart->has($this->product))->toBeTrue();

        // Create new cart instance to test persistence
        $newCart = LaravelMultiCart::cart('session_test', CartProvider::SESSION->value);
        expect($newCart->count())->toBe(2);
    });

    it('can delete cart from session', function () {
        $cart = LaravelMultiCart::cart('session_test', CartProvider::SESSION->value);
        $cart->add($this->product);

        expect($cart->exists())->toBeTrue();

        $cart->delete();

        expect(LaravelMultiCart::exists('session_test', CartProvider::SESSION->value))->toBeFalse();
    });
});

describe('Cache Provider', function () {
    it('can create cache cart provider', function () {
        $manager = app(CartManager::class);
        $provider = $manager->getProvider(CartProvider::CACHE->value);

        expect($provider)->toBeInstanceOf(CacheCartProvider::class);
    });

    it('can store and retrieve cart data in cache', function () {
        // Clear any existing cache data first
        cache()->flush();

        // Use unique cart name to avoid interference
        $uniqueId = uniqid();
        $cart = LaravelMultiCart::cart("cache_test_{$uniqueId}", CartProvider::CACHE->value);
        $cart->add($this->product, 3);

        expect($cart->count())->toBe(3)
            ->and($cart->has($this->product))->toBeTrue();

        // Create a new cart manager instance to simulate a fresh request
        $newManager = new CartManager(app());
        $newCart = $newManager->cart("cache_test_{$uniqueId}", CartProvider::CACHE->value);
        expect($newCart->count())->toBe(3);
    });

    it('can delete cart from cache', function () {
        $cart = LaravelMultiCart::cart('cache_test', CartProvider::CACHE->value);
        $cart->add($this->product);

        expect($cart->exists())->toBeTrue();

        $cart->delete();

        expect(LaravelMultiCart::exists('cache_test', CartProvider::CACHE->value))->toBeFalse();
    });
});

describe('Database Provider', function () {
    it('can create database cart provider', function () {
        $manager = app(CartManager::class);
        $provider = $manager->getProvider(CartProvider::DATABASE->value);

        expect($provider)->toBeInstanceOf(DatabaseCartProvider::class);
    });

    it('can store and retrieve cart data in database', function () {
        $cart = LaravelMultiCart::cart('db_test', CartProvider::DATABASE->value);
        $cart->add($this->product, 4);

        expect($cart->count())->toBe(4)
            ->and($cart->has($this->product))->toBeTrue();

        // Verify database record exists
        $dbCart = \HCart\LaravelMultiCart\Models\Cart::where('name', 'db_test')->first();
        expect($dbCart)->not()->toBeNull()
            ->and($dbCart->name)->toBe('db_test');

        // Create new cart instance to test persistence
        $newCart = LaravelMultiCart::cart('db_test', CartProvider::DATABASE->value);
        expect($newCart->count())->toBe(4);
    });

    it('can soft delete cart from database', function () {
        $cart = LaravelMultiCart::cart('db_test', CartProvider::DATABASE->value);
        $cart->add($this->product);

        expect($cart->exists())->toBeTrue();

        $cart->delete();

        expect(LaravelMultiCart::exists('db_test', CartProvider::DATABASE->value))->toBeFalse();

        // Check that cart is soft deleted
        $dbCart = \HCart\LaravelMultiCart\Models\Cart::withTrashed()->where('name', 'db_test')->first();
        expect($dbCart)->not()->toBeNull()
            ->and($dbCart->deleted_at)->not()->toBeNull();
    });

    it('stores user morph relationship correctly', function () {
        $user = \HCart\LaravelMultiCart\Tests\Fixtures\User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $cart = LaravelMultiCart::cart('user_test', CartProvider::DATABASE->value)->forUser($user);
        $cart->add($this->product);

        $dbCart = \HCart\LaravelMultiCart\Models\Cart::where('name', 'user_test')->first();
        expect($dbCart->user_id)->toBe($user->id)
            ->and($dbCart->user_type)->toBe(get_class($user))
            ->and($dbCart->user->id)->toBe($user->id);
    });
});

describe('File Provider', function () {
    it('can create file cart provider', function () {
        $manager = app(CartManager::class);
        $provider = $manager->getProvider(CartProvider::FILE->value);

        expect($provider)->toBeInstanceOf(FileCartProvider::class);
    });

    it('can store and retrieve cart data in files', function () {
        // Use unique cart name to avoid interference
        $uniqueId = uniqid();
        $cart = LaravelMultiCart::cart("file_test_{$uniqueId}", CartProvider::FILE->value);
        $cart->add($this->product, 5);

        expect($cart->count())->toBe(5)
            ->and($cart->has($this->product))->toBeTrue();

        // Create new cart instance to test persistence
        $newCart = LaravelMultiCart::cart("file_test_{$uniqueId}", CartProvider::FILE->value);
        expect($newCart->count())->toBe(5);
    });

    it('can delete cart file', function () {
        $cart = LaravelMultiCart::cart('file_test', CartProvider::FILE->value);
        $cart->add($this->product);

        expect($cart->exists())->toBeTrue();

        $cart->delete();

        expect(LaravelMultiCart::exists('file_test', CartProvider::FILE->value))->toBeFalse();
    });
});

describe('Provider Switching', function () {
    it('can use different providers for different carts', function () {
        // Clear cache to avoid interference from previous tests
        cache()->flush();

        // Use unique cart names to avoid cart manager caching issues
        $uniqueId = uniqid();
        $sessionCart = LaravelMultiCart::cart("session_cart_{$uniqueId}", CartProvider::SESSION->value);
        $dbCart = LaravelMultiCart::cart("db_cart_{$uniqueId}", CartProvider::DATABASE->value);
        $cacheCart = LaravelMultiCart::cart("cache_cart_{$uniqueId}", CartProvider::CACHE->value);

        $sessionCart->add($this->product, 1);
        $dbCart->add($this->product, 2);
        $cacheCart->add($this->product, 3);

        expect($sessionCart->count())->toBe(1)
            ->and($dbCart->count())->toBe(2)
            ->and($cacheCart->count())->toBe(3);

        expect($sessionCart->getProvider())->toBe(CartProvider::SESSION->value)
            ->and($dbCart->getProvider())->toBe(CartProvider::DATABASE->value)
            ->and($cacheCart->getProvider())->toBe(CartProvider::CACHE->value);
    });

    it('can create cart with custom configuration', function () {
        $cart = LaravelMultiCart::create('custom', [
            'currency' => 'EUR',
            'tax_rate' => 0.20,
        ], CartProvider::DATABASE->value);

        expect($cart->getConfig())->toBe(['currency' => 'EUR', 'tax_rate' => 0.20])
            ->and($cart->getProvider())->toBe(CartProvider::DATABASE->value);
    });
});

describe('Provider Flushing', function () {
    beforeEach(function () {
        // Create carts in different providers
        LaravelMultiCart::cart('session1', CartProvider::SESSION->value)->add($this->product);
        LaravelMultiCart::cart('session2', CartProvider::SESSION->value)->add($this->product);
        LaravelMultiCart::cart('db1', CartProvider::DATABASE->value)->add($this->product);
        LaravelMultiCart::cart('db2', CartProvider::DATABASE->value)->add($this->product);
    });

    it('can flush all carts from specific provider', function () {
        expect(LaravelMultiCart::exists('session1', CartProvider::SESSION->value))->toBeTrue()
            ->and(LaravelMultiCart::exists('session2', CartProvider::SESSION->value))->toBeTrue()
            ->and(LaravelMultiCart::exists('db1', CartProvider::DATABASE->value))->toBeTrue()
            ->and(LaravelMultiCart::exists('db2', CartProvider::DATABASE->value))->toBeTrue();

        LaravelMultiCart::flush(CartProvider::SESSION->value);

        expect(LaravelMultiCart::exists('session1', CartProvider::SESSION->value))->toBeFalse()
            ->and(LaravelMultiCart::exists('session2', CartProvider::SESSION->value))->toBeFalse()
            ->and(LaravelMultiCart::exists('db1', CartProvider::DATABASE->value))->toBeTrue()
            ->and(LaravelMultiCart::exists('db2', CartProvider::DATABASE->value))->toBeTrue();
    });
});
