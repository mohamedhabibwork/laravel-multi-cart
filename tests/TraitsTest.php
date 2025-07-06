<?php

use HCart\LaravelMultiCart\Enums\CartProvider;
use HCart\LaravelMultiCart\Facades\LaravelMultiCart;
use HCart\LaravelMultiCart\Models\Cart;
use HCart\LaravelMultiCart\Tests\Fixtures\Product;
use HCart\LaravelMultiCart\Tests\Fixtures\User;

beforeEach(function () {
    $this->user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
    ]);

    $this->product = Product::create([
        'name' => 'Test Product',
        'price' => 29.99,
        'sku' => 'TEST-001',
    ]);
});

describe('HasCarts Trait', function () {
    it('has carts relationship', function () {
        // Create carts for the user
        $cart1 = Cart::create([
            'name' => 'shopping',
            'user_id' => $this->user->id,
            'user_type' => get_class($this->user),
        ]);

        $cart2 = Cart::create([
            'name' => 'wishlist',
            'user_id' => $this->user->id,
            'user_type' => get_class($this->user),
        ]);

        // Create cart for different user
        $otherUser = User::create(['name' => 'Other User', 'email' => 'other@example.com']);
        Cart::create([
            'name' => 'other_cart',
            'user_id' => $otherUser->id,
            'user_type' => get_class($otherUser),
        ]);

        $userCarts = $this->user->carts;

        expect($userCarts)->toHaveCount(2)
            ->and($userCarts->pluck('name')->toArray())->toEqual(['shopping', 'wishlist']);
    });

    it('can get user cart', function () {
        $cart = $this->user->getCart('shopping', CartProvider::DATABASE->value);
        $cart->add($this->product, 2);

        expect($cart->getName())->toBe('shopping')
            ->and($cart->count())->toBe(2);

        // Verify cart is associated with user
        $dbCart = Cart::where('name', 'shopping')->first();
        expect($dbCart->user_id)->toBe($this->user->id)
            ->and($dbCart->user_type)->toBe(get_class($this->user));
    });

    it('can create user cart', function () {
        $cart = $this->user->createCart('new_cart', ['currency' => 'EUR'], CartProvider::DATABASE->value);

        expect($cart->getName())->toBe('new_cart')
            ->and($cart->getProvider())->toBe(CartProvider::DATABASE->value)
            ->and($cart->getConfig())->toBe(['currency' => 'EUR']);

        // Verify cart is in database with user association
        $dbCart = Cart::where('name', 'new_cart')->first();
        expect($dbCart)->not()->toBeNull()
            ->and($dbCart->user_id)->toBe($this->user->id)
            ->and($dbCart->user_type)->toBe(get_class($this->user));
    });

    it('can delete user cart', function () {
        $cart = $this->user->createCart('temp_cart');
        $cart->add($this->product);

        expect($this->user->hasCart('temp_cart'))->toBeTrue();

        $deleted = $this->user->deleteCart('temp_cart');

        expect($deleted)->toBeTrue()
            ->and($this->user->hasCart('temp_cart'))->toBeFalse();
    });

    it('can get cart names', function () {
        $this->user->createCart('cart1', [], CartProvider::DATABASE->value);
        $this->user->createCart('cart2', [], CartProvider::DATABASE->value);
        $this->user->createCart('cart3', [], CartProvider::DATABASE->value);

        $cartNames = $this->user->getCartNames();

        expect($cartNames)->toHaveCount(3)
            ->and($cartNames)->toEqual(['cart1', 'cart2', 'cart3']);
    });

    it('can check if user has cart', function () {
        expect($this->user->hasCart('nonexistent'))->toBeFalse();

        $this->user->createCart('existing_cart');

        expect($this->user->hasCart('existing_cart'))->toBeTrue();
    });

    it('can clone user cart', function () {
        $originalCart = $this->user->createCart('original');
        $originalCart->add($this->product, 3, ['size' => 'large']);

        $clonedCart = $this->user->cloneCart('original', 'cloned');

        expect($clonedCart->getName())->toBe('cloned')
            ->and($clonedCart->count())->toBe(3)
            ->and($clonedCart->has($this->product))->toBeTrue();

        // Both carts should exist
        expect($this->user->hasCart('original'))->toBeTrue()
            ->and($this->user->hasCart('cloned'))->toBeTrue();
    });

    it('can convert user cart to different provider', function () {
        $cart = $this->user->createCart('convertible', [], CartProvider::SESSION->value);
        $cart->add($this->product, 2);

        expect($cart->getProvider())->toBe(CartProvider::SESSION->value);

        $convertedCart = $this->user->convertCartToProvider('convertible', CartProvider::DATABASE->value);

        expect($convertedCart->getProvider())->toBe(CartProvider::DATABASE->value)
            ->and($convertedCart->count())->toBe(2);

        // Original session cart should no longer exist
        expect(LaravelMultiCart::exists('convertible', CartProvider::SESSION->value))->toBeFalse()
            ->and(LaravelMultiCart::exists('convertible', CartProvider::DATABASE->value))->toBeTrue();
    });
});

describe('Cartable Trait', function () {
    it('has cart items relationship', function () {
        $cart = LaravelMultiCart::cart('test_cart', CartProvider::DATABASE->value);
        $cart->add($this->product, 2);

        $cartItems = $this->product->cartItems;

        expect($cartItems)->toHaveCount(1)
            ->and($cartItems->first()->quantity)->toBe(2)
            ->and($cartItems->first()->cartable->id)->toBe($this->product->id)
            ->and($cartItems->first()->cartable_type)->toBe(get_class($this->product));
    });

    it('can check if product is in cart', function () {
        expect($this->product->isInCart())->toBeFalse();

        $cart = LaravelMultiCart::cart('test_cart', CartProvider::DATABASE->value);
        $cart->add($this->product);

        expect($this->product->isInCart())->toBeTrue();
    });

    it('can check if product is in specific cart', function () {
        $cart1 = LaravelMultiCart::cart('cart1', CartProvider::DATABASE->value);
        $cart2 = LaravelMultiCart::cart('cart2', CartProvider::DATABASE->value);

        $cart1->add($this->product);

        expect($this->product->isInCart('cart1'))->toBeTrue()
            ->and($this->product->isInCart('cart2'))->toBeFalse()
            ->and($this->product->isInCart())->toBeTrue(); // Any cart
    });

    it('can get cart quantity', function () {
        $cart1 = LaravelMultiCart::cart('cart1', CartProvider::DATABASE->value);
        $cart2 = LaravelMultiCart::cart('cart2', CartProvider::DATABASE->value);

        $cart1->add($this->product, 2);
        $cart2->add($this->product, 3);

        expect($this->product->getCartQuantity())->toBe(5) // Total across all carts
            ->and($this->product->getCartQuantity('cart1'))->toBe(2)
            ->and($this->product->getCartQuantity('cart2'))->toBe(3);
    });

    it('can remove product from cart', function () {
        $cart1 = LaravelMultiCart::cart('cart1', CartProvider::DATABASE->value);
        $cart2 = LaravelMultiCart::cart('cart2', CartProvider::DATABASE->value);

        $cart1->add($this->product, 2);
        $cart2->add($this->product, 3);

        expect($this->product->isInCart('cart1'))->toBeTrue()
            ->and($this->product->isInCart('cart2'))->toBeTrue();

        // Remove from specific cart
        $removed = $this->product->removeFromCart('cart1');

        expect($removed)->toBeTrue()
            ->and($this->product->isInCart('cart1'))->toBeFalse()
            ->and($this->product->isInCart('cart2'))->toBeTrue();

        // Remove from all carts
        $removedAll = $this->product->removeFromCart();

        expect($removedAll)->toBeTrue()
            ->and($this->product->isInCart())->toBeFalse();
    });

    it('returns correct cart price', function () {
        expect($this->product->getCartPrice())->toBe(29.99);
    });

    it('returns correct cart name', function () {
        expect($this->product->getCartName())->toBe('Test Product');
    });

    it('returns correct cart attributes', function () {
        $attributes = $this->product->getCartAttributes();

        expect($attributes)->toBeArray()
            ->and($attributes)->toBe(['sku' => 'TEST-001']);
    });

    it('works with different cartable models', function () {
        // Create another cartable model type
        $anotherProduct = Product::create([
            'name' => 'Another Product',
            'price' => 19.99,
            'sku' => 'TEST-002',
        ]);

        $cart = LaravelMultiCart::cart('mixed_cart', CartProvider::DATABASE->value);
        $cart->add($this->product, 1);
        $cart->add($anotherProduct, 2);

        expect($cart->count())->toBe(3)
            ->and($this->product->isInCart('mixed_cart'))->toBeTrue()
            ->and($anotherProduct->isInCart('mixed_cart'))->toBeTrue()
            ->and($this->product->getCartQuantity('mixed_cart'))->toBe(1)
            ->and($anotherProduct->getCartQuantity('mixed_cart'))->toBe(2);
    });

    it('handles cart items with attributes', function () {
        // Create cart with custom unique callback that considers attributes
        $config = new \HCart\LaravelMultiCart\Config\LaravelMultiCartConfig([
            'callbacks' => [
                'unique_item' => function ($cartableId, $cartableType, $attributes) {
                    return md5($cartableId.$cartableType.json_encode($attributes));
                },
            ],
        ]);

        $cart = LaravelMultiCart::cart('attr_cart', CartProvider::DATABASE->value)->withConfig($config);
        $cart->add($this->product, 1, ['size' => 'large', 'color' => 'red']);
        $cart->add($this->product, 2, ['size' => 'medium', 'color' => 'blue']);

        // Should have 2 separate items due to different attributes
        $cartItems = $this->product->cartItems()->where('cart_id', $cart->getCartId())->get();

        expect($cartItems)->toHaveCount(2)
            ->and($this->product->getCartQuantity('attr_cart'))->toBe(3);
    });
});
