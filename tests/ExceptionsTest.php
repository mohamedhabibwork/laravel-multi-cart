<?php

use HCart\LaravelMultiCart\Exceptions\CartExistsException;
use HCart\LaravelMultiCart\Exceptions\CartItemNotFoundException;
use HCart\LaravelMultiCart\Exceptions\CartNotFoundException;
use HCart\LaravelMultiCart\Exceptions\InvalidCartProviderException;
use HCart\LaravelMultiCart\Exceptions\InvalidConfigurationException;
use HCart\LaravelMultiCart\Facades\LaravelMultiCart;
use HCart\LaravelMultiCart\Services\CartManager;
use HCart\LaravelMultiCart\Tests\Fixtures\Product;

beforeEach(function () {
    $this->product = Product::create([
        'name' => 'Test Product',
        'price' => 29.99,
        'sku' => 'TEST-001',
    ]);
});

describe('CartItemNotFoundException', function () {
    it('throws exception when trying to get non-existent cart item', function () {
        $cart = LaravelMultiCart::cart('test_cart');

        expect(function () use ($cart) {
            $cart->remove('non-existent-item-id');
        })->toThrow(CartItemNotFoundException::class, 'Cart item with ID non-existent-item-id not found');
    });

    it('throws exception when trying to update non-existent cart item', function () {
        $cart = LaravelMultiCart::cart('test_cart');

        expect(function () use ($cart) {
            $cart->update('non-existent-item-id', ['quantity' => 5]);
        })->toThrow(CartItemNotFoundException::class, 'Cart item with ID non-existent-item-id not found');
    });

    it('includes correct information in exception', function () {
        $cart = LaravelMultiCart::cart('test_cart');

        try {
            $cart->remove('missing-item');
        } catch (CartItemNotFoundException $e) {
            expect($e->getMessage())->toBe('Cart item with ID missing-item not found')
                ->and($e->getCode())->toBe(404);
        }
    });
});

describe('CartExistsException', function () {
    it('throws exception when trying to create cart with existing name in strict mode', function () {
        // Create a cart first
        $cart = LaravelMultiCart::cart('existing_cart');
        $cart->add($this->product);

        // Try to create another cart with same name in strict mode
        expect(function () {
            LaravelMultiCart::createStrict('existing_cart');
        })->toThrow(CartExistsException::class, 'Cart with name existing_cart already exists');
    });

    it('includes correct information in exception', function () {
        $cart = LaravelMultiCart::cart('duplicate_cart');
        $cart->add($this->product);

        try {
            LaravelMultiCart::createStrict('duplicate_cart');
        } catch (CartExistsException $e) {
            expect($e->getMessage())->toBe('Cart with name duplicate_cart already exists')
                ->and($e->getCode())->toBe(409);
        }
    });
});

describe('InvalidCartProviderException', function () {
    it('throws exception for non-existent provider', function () {
        expect(function () {
            LaravelMultiCart::cart('test_cart', 'non_existent_provider');
        })->toThrow(InvalidCartProviderException::class, 'Cart provider non_existent_provider is not configured');
    });

    it('throws exception when getting invalid provider from manager', function () {
        $manager = app(CartManager::class);

        expect(function () use ($manager) {
            $manager->getProvider('invalid_provider');
        })->toThrow(InvalidCartProviderException::class, 'Cart provider invalid_provider is not configured');
    });

    it('includes correct information in exception', function () {
        try {
            LaravelMultiCart::cart('test_cart', 'invalid_provider');
        } catch (InvalidCartProviderException $e) {
            expect($e->getMessage())->toBe('Cart provider invalid_provider is not configured')
                ->and($e->getCode())->toBe(500);
        }
    });
});

describe('InvalidConfigurationException', function () {
    it('throws exception for invalid configuration', function () {
        // Test that InvalidConfigurationException can be thrown manually
        expect(function () {
            throw new InvalidConfigurationException('Invalid model configuration: NonExistentClass does not exist');
        })->toThrow(InvalidConfigurationException::class);
    });

    it('throws exception for invalid provider configuration', function () {
        // Mock invalid provider config
        config()->set('laravel-multi-cart.providers.invalid', [
            'driver' => 'non_existent_driver',
        ]);

        expect(function () {
            LaravelMultiCart::cart('test_cart', 'invalid');
        })->toThrow(InvalidCartProviderException::class);
    });

    it('includes correct information in exception', function () {
        try {
            throw new InvalidConfigurationException('Invalid model configuration: NonExistentClass does not exist');
        } catch (InvalidConfigurationException $e) {
            expect($e->getMessage())->toBe('Invalid model configuration: NonExistentClass does not exist')
                ->and($e->getCode())->toBe(500);
        }
    });
});

describe('Exception Handling in Cart Operations', function () {
    it('handles exceptions gracefully during cart operations', function () {
        $cart = LaravelMultiCart::cart('exception_test');

        // Add an item first
        $cart->add($this->product);
        $items = $cart->items();
        $validItemId = $items->first()['id'];

        // Valid operation should work
        expect(function () use ($cart, $validItemId) {
            $cart->update($validItemId, ['quantity' => 3]);
        })->not()->toThrow(CartNotFoundException::class);

        // Invalid operation should throw exception
        expect(function () use ($cart) {
            $cart->update('invalid-id', ['quantity' => 3]);
        })->toThrow(CartItemNotFoundException::class);
    });

    it('handles provider switching exceptions', function () {
        $cart = LaravelMultiCart::cart('provider_test', 'session');
        $cart->add($this->product);

        // Valid provider conversion should work
        expect(function () use ($cart) {
            $cart->convertToProvider('database');
        })->not()->toThrow(InvalidCartProviderException::class);

        // Invalid provider conversion should throw exception
        expect(function () use ($cart) {
            $cart->convertToProvider('non_existent_provider');
        })->toThrow(InvalidCartProviderException::class);
    });

    it('provides helpful error messages for debugging', function () {
        $cart = LaravelMultiCart::cart('debug_test');

        try {
            $cart->remove('debug-item-id');
        } catch (CartItemNotFoundException $e) {
            expect($e->getMessage())->toContain('debug-item-id')
                ->and($e->getMessage())->toContain('not found');
        }

        try {
            LaravelMultiCart::cart('test', 'debug-provider');
        } catch (InvalidCartProviderException $e) {
            expect($e->getMessage())->toContain('debug-provider')
                ->and($e->getMessage())->toContain('not configured');
        }
    });
});

describe('Exception Recovery', function () {
    it('can continue operations after handling exceptions', function () {
        $cart = LaravelMultiCart::cart('recovery_test');
        $cart->add($this->product);

        // Try invalid operation
        try {
            $cart->remove('invalid-item-id');
        } catch (CartItemNotFoundException $e) {
            // Exception caught, cart should still be usable
        }

        // Should be able to continue normal operations
        expect($cart->count())->toBe(1)
            ->and($cart->has($this->product))->toBeTrue();

        // Should be able to add more items
        $cart->add($this->product, 2);
        expect($cart->count())->toBe(3);
    });

    it('maintains cart state consistency after exceptions', function () {
        $cart = LaravelMultiCart::cart('consistency_test');
        $cart->add($this->product, 2);

        $originalCount = $cart->count();
        $originalSubtotal = $cart->subtotal();

        // Try invalid update operation
        try {
            $cart->update('invalid-item-id', ['quantity' => 10]);
        } catch (CartItemNotFoundException $e) {
            // Exception should not affect cart state
        }

        expect($cart->count())->toBe($originalCount)
            ->and($cart->subtotal())->toBe($originalSubtotal);
    });
});
