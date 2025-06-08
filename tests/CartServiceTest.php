<?php

use HCart\LaravelMultiCart\Events\CartCreated;
use HCart\LaravelMultiCart\Events\ItemAdded;
use HCart\LaravelMultiCart\Events\ItemRemoved;
use HCart\LaravelMultiCart\Events\ItemUpdated;
use HCart\LaravelMultiCart\Facades\LaravelMultiCart;
use HCart\LaravelMultiCart\Tests\Fixtures\Product;
use HCart\LaravelMultiCart\Tests\Fixtures\User;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->product = Product::create([
        'name' => 'Test Product',
        'price' => 29.99,
        'sku' => 'TEST-001',
    ]);

    $this->user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
    ]);
});

describe('Cart Basic Operations', function () {
    it('can create a new cart', function () {
        Event::fake();

        $cart = LaravelMultiCart::cart('shopping');

        expect($cart)->toBeInstanceOf(\HCart\LaravelMultiCart\Services\CartService::class)
            ->and($cart->getName())->toBe('shopping')
            ->and($cart->getProvider())->toBe('session');

        // Access the cart to trigger loading and creation event
        $cart->count();

        Event::assertDispatched(CartCreated::class);
    });

    it('can add items to cart', function () {
        Event::fake();

        $cart = LaravelMultiCart::cart('shopping');
        $cart->add($this->product, 2);

        expect($cart->count())->toBe(2)
            ->and($cart->has($this->product))->toBeTrue()
            ->and($cart->quantity($this->product))->toBe(2);

        Event::assertDispatched(ItemAdded::class);
    });

    it('can add items with attributes', function () {
        $cart = LaravelMultiCart::cart('shopping');
        $cart->add($this->product, 1, ['size' => 'large', 'color' => 'red']);

        $items = $cart->items();
        expect($items)->toHaveCount(1)
            ->and($items->first()['attributes'])->toBe(['size' => 'large', 'color' => 'red']);
    });

    it('can update item quantity', function () {
        Event::fake();

        $cart = LaravelMultiCart::cart('shopping');
        $cart->add($this->product, 2);

        $items = $cart->items();
        $itemId = $items->first()['id'];

        $cart->update($itemId, ['quantity' => 5]);

        expect($cart->count())->toBe(5)
            ->and($cart->quantity($this->product))->toBe(5);

        Event::assertDispatched(ItemUpdated::class);
    });

    it('can remove items from cart', function () {
        Event::fake();

        $cart = LaravelMultiCart::cart('shopping');
        $cart->add($this->product, 2);

        $items = $cart->items();
        $itemId = $items->first()['id'];

        $result = $cart->remove($itemId);

        expect($result)->toBeTrue()
            ->and($cart->count())->toBe(0)
            ->and($cart->has($this->product))->toBeFalse();

        Event::assertDispatched(ItemRemoved::class);
    });

    it('can clear all items', function () {
        $cart = LaravelMultiCart::cart('shopping');
        $cart->add($this->product, 2);

        expect($cart->count())->toBe(2);

        $cart->clear();

        expect($cart->count())->toBe(0)
            ->and($cart->items())->toHaveCount(0);
    });
});

describe('Cart Calculations', function () {
    beforeEach(function () {
        config()->set('laravel-multi-cart.tax_rate', 0.10); // 10% tax
    });

    it('calculates subtotal correctly', function () {
        $cart = LaravelMultiCart::cart('shopping');
        $cart->add($this->product, 2); // 2 * 29.99 = 59.98

        expect($cart->subtotal())->toBe(59.98);
    });

    it('calculates tax correctly', function () {
        $cart = LaravelMultiCart::cart('shopping');
        $cart->add($this->product, 2); // 2 * 29.99 = 59.98, tax = 5.998

        expect($cart->tax())->toBe(5.998);
    });

    it('calculates total correctly', function () {
        $cart = LaravelMultiCart::cart('shopping');
        $cart->add($this->product, 2); // 59.98 + 5.998 = 65.978

        expect($cart->total())->toBe(65.978);
    });
});

describe('Cart User Association', function () {
    it('can associate cart with user', function () {
        $cart = LaravelMultiCart::cart('shopping')->forUser($this->user);
        $cart->add($this->product);

        // Cart should be associated with user
        expect($this->user->hasCart('shopping'))->toBeTrue();
    });

    it('can get user cart', function () {
        $cart = $this->user->getCart('shopping');
        $cart->add($this->product, 3);

        $userCart = $this->user->getCart('shopping');

        expect($userCart->count())->toBe(3)
            ->and($userCart->has($this->product))->toBeTrue();
    });
});

describe('Cart Cloning', function () {
    it('can clone cart to new name', function () {
        $cart = LaravelMultiCart::cart('shopping');
        $cart->add($this->product, 2, ['size' => 'large']);
        $cart->setConfig(['currency' => 'EUR']);

        $clonedCart = $cart->clone('shopping_backup');

        expect($clonedCart->getName())->toBe('shopping_backup')
            ->and($clonedCart->count())->toBe(2)
            ->and($clonedCart->has($this->product))->toBeTrue()
            ->and($clonedCart->getConfig())->toBe(['currency' => 'EUR']);
    });

    it('can clone cart to different provider', function () {
        $cart = LaravelMultiCart::cart('shopping', 'session');
        $cart->add($this->product, 2);

        $clonedCart = $cart->clone('shopping_backup', 'database');

        expect($clonedCart->getName())->toBe('shopping_backup')
            ->and($clonedCart->getProvider())->toBe('database')
            ->and($clonedCart->count())->toBe(2);
    });
});

describe('Cart Provider Conversion', function () {
    it('can convert cart to different provider', function () {
        $cart = LaravelMultiCart::cart('shopping', 'session');
        $cart->add($this->product, 2);
        $cart->setConfig(['currency' => 'USD']);

        $convertedCart = $cart->convertToProvider('database');

        expect($convertedCart->getProvider())->toBe('database')
            ->and($convertedCart->getName())->toBe('shopping')
            ->and($convertedCart->count())->toBe(2)
            ->and($convertedCart->getConfig())->toBe(['currency' => 'USD']);

        // Original cart should no longer exist in session provider
        $originalExists = LaravelMultiCart::exists('shopping', 'session');
        expect($originalExists)->toBeFalse();
    });

    it('returns same cart if converting to same provider', function () {
        $cart = LaravelMultiCart::cart('shopping', 'session');
        $convertedCart = $cart->convertToProvider('session');

        expect($convertedCart)->toBe($cart);
    });
});

describe('Cart Configuration', function () {
    it('can set and get cart configuration', function () {
        $cart = LaravelMultiCart::cart('shopping');
        $config = ['currency' => 'EUR', 'tax_rate' => 0.15];

        $cart->setConfig($config);

        expect($cart->getConfig())->toBe($config);
    });

    it('merges configuration when setting multiple times', function () {
        $cart = LaravelMultiCart::cart('shopping');

        $cart->setConfig(['currency' => 'EUR']);
        $cart->setConfig(['tax_rate' => 0.15]);

        expect($cart->getConfig())->toBe(['currency' => 'EUR', 'tax_rate' => 0.15]);
    });
});

describe('Cart Existence and Deletion', function () {
    it('can check if cart exists', function () {
        expect(LaravelMultiCart::exists('nonexistent'))->toBeFalse();

        $cart = LaravelMultiCart::cart('shopping');
        $cart->add($this->product);

        expect($cart->exists())->toBeTrue()
            ->and(LaravelMultiCart::exists('shopping'))->toBeTrue();
    });

    it('can delete cart', function () {
        $cart = LaravelMultiCart::cart('shopping');
        $cart->add($this->product);

        expect($cart->exists())->toBeTrue();

        $deleted = $cart->delete();

        expect($deleted)->toBeTrue()
            ->and(LaravelMultiCart::exists('shopping'))->toBeFalse();
    });
});
