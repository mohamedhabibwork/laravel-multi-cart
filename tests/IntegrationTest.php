<?php

use HCart\LaravelMultiCart\Config\LaravelMultiCartConfig;
use HCart\LaravelMultiCart\Events\CartCreated;
use HCart\LaravelMultiCart\Events\ItemAdded;
use HCart\LaravelMultiCart\Events\ItemUpdated;
use HCart\LaravelMultiCart\Facades\LaravelMultiCart;
use HCart\LaravelMultiCart\Tests\Fixtures\Product;
use HCart\LaravelMultiCart\Tests\Fixtures\User;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->user1 = User::create([
        'name' => 'John Doe',
        'email' => 'john@example.com',
    ]);

    $this->user2 = User::create([
        'name' => 'Jane Smith',
        'email' => 'jane@example.com',
    ]);

    $this->products = collect([
        Product::create(['name' => 'Laptop', 'price' => 999.99, 'sku' => 'LAPTOP-001']),
        Product::create(['name' => 'Mouse', 'price' => 29.99, 'sku' => 'MOUSE-001']),
        Product::create(['name' => 'Keyboard', 'price' => 79.99, 'sku' => 'KEYBOARD-001']),
        Product::create(['name' => 'Monitor', 'price' => 299.99, 'sku' => 'MONITOR-001']),
    ]);
});

describe('Complete Shopping Cart Workflow', function () {
    it('can handle complete shopping cart scenario', function () {
        Event::fake();

        // User creates shopping cart
        $cart = $this->user1->createCart('shopping', ['currency' => 'USD'], 'database');

        // Add products with different quantities and attributes
        $cart->add($this->products[0], 1, ['warranty' => '2-year']); // Laptop
        $cart->add($this->products[1], 2, ['color' => 'black']); // Mouse x2
        $cart->add($this->products[2], 1, ['layout' => 'US']); // Keyboard

        // Verify cart state
        expect($cart->count())->toBe(4) // Total items
            ->and($cart->items())->toHaveCount(3) // Unique items
            ->and($cart->subtotal())->toBe(1139.96) // 999.99 + (29.99*2) + 79.99
            ->and($cart->has($this->products[0]))->toBeTrue()
            ->and($cart->has($this->products[3]))->toBeFalse();

        // User decides to add more mice
        $cart->add($this->products[1], 1, ['color' => 'black']); // Same attributes, should increase quantity

        expect($cart->count())->toBe(5)
            ->and($cart->quantity($this->products[1]))->toBe(3);

        // User changes their mind about the laptop warranty
        $items = $cart->items();
        $laptopItem = $items->first(fn ($item) => $item['cartable_id'] === $this->products[0]->id);

        $cart->update($laptopItem['id'], [
            'attributes' => ['warranty' => '3-year'],
            'price' => 1099.99, // Premium for extended warranty
        ]);

        // User removes one mouse by updating quantity
        $mouseItem = $cart->items()->first(fn ($item) => $item['cartable_id'] === $this->products[1]->id);
        $cart->update($mouseItem['id'], ['quantity' => 2]);

        expect($cart->quantity($this->products[1]))->toBe(2);

        // Verify final cart state
        $finalSubtotal = 1099.99 + (29.99 * 2) + 79.99; // 1239.96
        expect($cart->subtotal())->toBe($finalSubtotal);

        // Verify events were dispatched
        Event::assertDispatched(CartCreated::class);
        Event::assertDispatched(ItemAdded::class);
        Event::assertDispatched(ItemUpdated::class);
    });

    it('can handle cart across multiple sessions', function () {
        // Simulate user adding items in first session
        $cart = $this->user1->createCart('persistent_cart', [], 'database');
        $cart->add($this->products[0], 1);
        $cart->add($this->products[1], 2);

        $originalCount = $cart->count();
        $originalSubtotal = $cart->subtotal();

        // Simulate new session - get cart again
        $cartInNewSession = $this->user1->getCart('persistent_cart');

        expect($cartInNewSession->count())->toBe($originalCount)
            ->and($cartInNewSession->subtotal())->toBe($originalSubtotal)
            ->and($cartInNewSession->has($this->products[0]))->toBeTrue()
            ->and($cartInNewSession->has($this->products[1]))->toBeTrue();

        // Add more items in new session
        $cartInNewSession->add($this->products[2], 1);

        expect($cartInNewSession->count())->toBe($originalCount + 1);
    });
});

describe('Multi-User Cart Scenarios', function () {
    it('isolates carts between different users', function () {
        // User 1 creates shopping cart
        $user1Cart = $this->user1->createCart('user1_shopping', [], 'database');
        $user1Cart->add($this->products[0], 1);
        $user1Cart->add($this->products[1], 2);

        // User 2 creates shopping cart with different name (since cart manager caches by name+provider)
        $user2Cart = $this->user2->createCart('user2_shopping', [], 'database');
        $user2Cart->add($this->products[2], 1);
        $user2Cart->add($this->products[3], 1);

        // Verify carts are isolated
        expect($user1Cart->count())->toBe(3)
            ->and($user2Cart->count())->toBe(2)
            ->and($user1Cart->has($this->products[0]))->toBeTrue()
            ->and($user1Cart->has($this->products[2]))->toBeFalse()
            ->and($user2Cart->has($this->products[2]))->toBeTrue()
            ->and($user2Cart->has($this->products[0]))->toBeFalse();

        // Verify user cart lists
        expect($this->user1->getCartNames())->toEqual(['user1_shopping'])
            ->and($this->user2->getCartNames())->toEqual(['user2_shopping']);
    });

    it('can share cart between users through conversion', function () {
        // User 1 creates cart
        $user1Cart = $this->user1->createCart('shared_cart', [], 'database');
        $user1Cart->add($this->products[0], 1);

        // Clone cart for user 2
        $user2Cart = $user1Cart->clone('shared_cart_copy');
        $user2Cart->forUser($this->user2);

        // Force the cloned cart to be saved with new user association
        $user2Cart->count(); // This triggers loading/saving with user association

        // Both users should have similar carts
        expect($this->user1->hasCart('shared_cart'))->toBeTrue()
            ->and($this->user2->hasCart('shared_cart_copy'))->toBeTrue()
            ->and($user1Cart->count())->toBe($user2Cart->count())
            ->and($user1Cart->has($this->products[0]))->toBeTrue()
            ->and($user2Cart->has($this->products[0]))->toBeTrue();
    });
});

describe('Multi-Provider Cart Operations', function () {
    it('can transfer cart between providers', function () {
        // Start with session cart
        $sessionCart = LaravelMultiCart::cart('transfer_test', 'session');
        $sessionCart->add($this->products[0], 1);
        $sessionCart->add($this->products[1], 2, ['color' => 'red']);
        $sessionCart->setConfig(['currency' => 'EUR']);

        $originalCount = $sessionCart->count();
        $originalConfig = $sessionCart->getConfig();

        // Convert to database
        $dbCart = $sessionCart->convertToProvider('database');

        expect($dbCart->getProvider())->toBe('database')
            ->and($dbCart->count())->toBe($originalCount)
            ->and($dbCart->getConfig())->toBe($originalConfig)
            ->and($dbCart->has($this->products[0]))->toBeTrue()
            ->and($dbCart->has($this->products[1]))->toBeTrue();

        // Verify original session cart is gone
        expect(LaravelMultiCart::exists('transfer_test', 'session'))->toBeFalse();

        // Convert to cache
        $cacheCart = $dbCart->convertToProvider('cache');

        expect($cacheCart->getProvider())->toBe('cache')
            ->and($cacheCart->count())->toBe($originalCount);
    });

    it('handles provider-specific features correctly', function () {
        // Database cart with user association
        $dbCart = LaravelMultiCart::cart('db_features', 'database');
        $dbCart->forUser($this->user1);
        $dbCart->add($this->products[0], 1);

        // Verify database record exists
        $cartModel = \HCart\LaravelMultiCart\Models\Cart::where('name', 'db_features')->first();
        expect($cartModel)->not()->toBeNull()
            ->and($cartModel->user_id)->toBe($this->user1->id)
            ->and($cartModel->user_type)->toBe(get_class($this->user1));

        // Session cart (no persistent user association)
        $sessionCart = LaravelMultiCart::cart('session_features', 'session');
        $sessionCart->forUser($this->user1);
        $sessionCart->add($this->products[1], 1);

        // Both should work for the user
        expect($this->user1->hasCart('db_features'))->toBeTrue()
            ->and($sessionCart->getUser())->toBe($this->user1);
    });
});

describe('Complex Configuration Scenarios', function () {
    it('handles different tax rates for different carts', function () {
        // US cart with US tax rate
        $usConfig = new LaravelMultiCartConfig(['tax_rate' => 0.08, 'currency' => 'USD']);
        $usCart = LaravelMultiCart::cart('us_cart')->withConfig($usConfig);
        $usCart->add($this->products[0], 1); // $999.99

        // EU cart with EU tax rate
        $euConfig = new LaravelMultiCartConfig(['tax_rate' => 0.20, 'currency' => 'EUR']);
        $euCart = LaravelMultiCart::cart('eu_cart')->withConfig($euConfig);
        $euCart->add($this->products[0], 1); // $999.99

        expect(round($usCart->tax(), 4))->toBe(79.9992) // 999.99 * 0.08
            ->and(round($euCart->tax(), 3))->toBe(199.998) // 999.99 * 0.20
            ->and(round($usCart->total(), 4))->toBe(1079.9892)
            ->and(round($euCart->total(), 3))->toBe(1199.988);
    });

    it('uses custom unique item callbacks', function () {
        // Cart with size-sensitive uniqueness
        $sizeConfig = new LaravelMultiCartConfig([
            'callbacks' => [
                'unique_item' => function ($cartableId, $cartableType, $attributes) {
                    return md5($cartableId.$cartableType.($attributes['size'] ?? ''));
                },
            ],
        ]);

        $cart = LaravelMultiCart::cart('size_cart')->withConfig($sizeConfig);

        // Add same product with different sizes
        $cart->add($this->products[0], 1, ['size' => 'small']);
        $cart->add($this->products[0], 1, ['size' => 'large']);
        $cart->add($this->products[0], 1, ['size' => 'small']); // Should increase quantity

        expect($cart->items())->toHaveCount(2) // Two different sizes
            ->and($cart->count())->toBe(3); // Total quantity
    });
});

describe('Real-World Shopping Scenarios', function () {
    it('handles shopping cart with promotions and discounts', function () {
        $cart = $this->user1->createCart('promo_cart');

        // Add regular priced items
        $cart->add($this->products[0], 1); // Laptop $999.99
        $cart->add($this->products[1], 2); // Mouse $29.99 each

        // Apply promotional pricing
        $items = $cart->items();
        $laptopItem = $items->first(fn ($item) => $item['cartable_id'] === $this->products[0]->id);

        // Apply 10% discount to laptop
        $cart->update($laptopItem['id'], [
            'price' => 899.99, // Discounted price
            'attributes' => ['discount' => '10%', 'promo_code' => 'SAVE10'],
        ]);

        $expectedSubtotal = 899.99 + (29.99 * 2); // 959.97
        expect($cart->subtotal())->toBe($expectedSubtotal);

        // Verify discount attributes are preserved
        $updatedItems = $cart->items();
        $updatedLaptop = $updatedItems->first(fn ($item) => $item['cartable_id'] === $this->products[0]->id);

        expect($updatedLaptop['attributes']['discount'])->toBe('10%')
            ->and($updatedLaptop['attributes']['promo_code'])->toBe('SAVE10');
    });

    it('handles wishlist to cart conversion', function () {
        // User creates wishlist
        $wishlist = $this->user1->createCart('wishlist');
        $wishlist->add($this->products[0], 1);
        $wishlist->add($this->products[2], 1);
        $wishlist->add($this->products[3], 1);

        // User creates shopping cart
        $shoppingCart = $this->user1->createCart('shopping');

        // Move items from wishlist to shopping cart
        $wishlistItems = $wishlist->items();
        foreach ($wishlistItems as $item) {
            $product = $this->products->firstWhere('id', $item['cartable_id']);
            $shoppingCart->add($product, $item['quantity'], $item['attributes']);
        }

        // Clear wishlist
        $wishlist->clear();

        expect($shoppingCart->count())->toBe(3)
            ->and($wishlist->count())->toBe(0)
            ->and($shoppingCart->has($this->products[0]))->toBeTrue()
            ->and($shoppingCart->has($this->products[2]))->toBeTrue()
            ->and($shoppingCart->has($this->products[3]))->toBeTrue();
    });

    it('handles cart abandonment and recovery', function () {
        // User creates cart and adds items
        $cart = $this->user1->createCart('abandoned_cart', [], 'database');
        $cart->add($this->products[0], 1);
        $cart->add($this->products[1], 2);

        // Simulate cart expiration
        $cartModel = \HCart\LaravelMultiCart\Models\Cart::where('name', 'abandoned_cart')->first();
        $cartModel->update(['expires_at' => now()->subHour()]);

        // User returns and recovers cart
        $recoveredCart = $this->user1->getCart('abandoned_cart');

        // Even though expired, cart should still be accessible (business logic dependent)
        expect($recoveredCart->count())->toBe(3)
            ->and($recoveredCart->has($this->products[0]))->toBeTrue();

        // Extend cart expiration
        $recoveredCart->setConfig(['expires_at' => now()->addDays(7)->toISOString()]);
    });
});

describe('Performance and Stress Tests', function () {
    it('handles cart with many items efficiently', function () {
        // Use a custom config that considers variant in uniqueness
        $config = new \HCart\LaravelMultiCart\Config\LaravelMultiCartConfig([
            'callbacks' => [
                'unique_item' => function ($cartableId, $cartableType, $attributes) {
                    return md5($cartableId.$cartableType.($attributes['variant'] ?? ''));
                },
            ],
        ]);

        $cart = LaravelMultiCart::cart('large_cart', 'database')->withConfig($config);

        // Add many items
        foreach ($this->products as $index => $product) {
            for ($i = 1; $i <= 25; $i++) {
                $cart->add($product, 1, ['variant' => "variant_$i"]);
            }
        }

        // Should have 100 unique items (4 products * 25 variants each)
        expect($cart->items())->toHaveCount(100)
            ->and($cart->count())->toBe(100);

        // Operations should still be fast
        $startTime = microtime(true);
        $subtotal = $cart->subtotal();
        $executionTime = microtime(true) - $startTime;

        expect($executionTime)->toBeLessThan(1.0) // Should complete in under 1 second
            ->and($subtotal)->toBeGreaterThan(0);
    });

    it('handles multiple concurrent cart operations', function () {
        $carts = [];

        // Create multiple carts
        for ($i = 1; $i <= 10; $i++) {
            $carts[] = LaravelMultiCart::cart("concurrent_cart_$i", 'database');
        }

        // Perform operations on all carts
        foreach ($carts as $index => $cart) {
            $cart->add($this->products[$index % count($this->products)], $index + 1);
        }

        // Verify all carts are working correctly
        foreach ($carts as $index => $cart) {
            expect($cart->count())->toBe($index + 1)
                ->and($cart->exists())->toBeTrue();
        }
    });
});
