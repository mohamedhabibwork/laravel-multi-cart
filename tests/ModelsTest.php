<?php

use Carbon\Carbon;
use HCart\LaravelMultiCart\Models\Cart;
use HCart\LaravelMultiCart\Models\CartItem;
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

describe('Cart Model', function () {
    it('can create a cart', function () {
        $cart = Cart::create([
            'name' => 'test_cart',
            'config' => ['currency' => 'USD'],
            'user_id' => $this->user->id,
            'user_type' => get_class($this->user),
            'session_id' => 'test_session',
        ]);

        expect($cart)->toBeInstanceOf(Cart::class)
            ->and($cart->name)->toBe('test_cart')
            ->and($cart->config)->toBe(['currency' => 'USD'])
            ->and($cart->user_id)->toBe($this->user->id)
            ->and($cart->user_type)->toBe(get_class($this->user));
    });

    it('casts config to array', function () {
        $cart = Cart::create([
            'name' => 'test_cart',
            'config' => ['currency' => 'EUR', 'tax_rate' => 0.20],
        ]);

        expect($cart->config)->toBeArray()
            ->and($cart->config)->toBe(['currency' => 'EUR', 'tax_rate' => 0.20]);
    });

    it('can set and get configuration values', function () {
        $cart = Cart::create(['name' => 'test_cart']);

        expect($cart->getConfigValue('currency'))->toBeNull();

        $cart->setConfigValue('currency', 'GBP');
        $cart->save();

        expect($cart->getConfigValue('currency'))->toBe('GBP')
            ->and($cart->config)->toBe(['currency' => 'GBP']);
    });

    it('can get configuration with default value', function () {
        $cart = Cart::create(['name' => 'test_cart']);

        expect($cart->getConfigValue('currency', 'USD'))->toBe('USD');
    });

    it('has items relationship', function () {
        $cart = Cart::create(['name' => 'test_cart']);

        $cartItem = CartItem::create([
            'cart_id' => $cart->id,
            'cartable_id' => $this->product->id,
            'cartable_type' => get_class($this->product),
            'quantity' => 2,
            'price' => 29.99,
        ]);

        expect($cart->items)->toHaveCount(1)
            ->and($cart->items->first()->id)->toBe($cartItem->id)
            ->and($cart->items->first()->cart_id)->toBe($cartItem->cart_id);
    });

    it('has user morph relationship', function () {
        $cart = Cart::create([
            'name' => 'test_cart',
            'user_id' => $this->user->id,
            'user_type' => get_class($this->user),
        ]);

        expect($cart->user->id)->toBe($this->user->id)
            ->and($cart->user->name)->toBe($this->user->name);
    });

    it('can check if cart is expired', function () {
        $expiredCart = Cart::create([
            'name' => 'expired_cart',
            'expires_at' => Carbon::now()->subHour(),
        ]);

        $activeCart = Cart::create([
            'name' => 'active_cart',
            'expires_at' => Carbon::now()->addHour(),
        ]);

        $neverExpiresCart = Cart::create([
            'name' => 'never_expires',
            'expires_at' => null,
        ]);

        expect($expiredCart->isExpired())->toBeTrue()
            ->and($activeCart->isExpired())->toBeFalse()
            ->and($neverExpiresCart->isExpired())->toBeFalse();
    });

    it('has active scope', function () {
        $expiredCart = Cart::create([
            'name' => 'expired_cart',
            'expires_at' => Carbon::now()->subHour(),
        ]);

        $activeCart = Cart::create([
            'name' => 'active_cart',
            'expires_at' => Carbon::now()->addHour(),
        ]);

        $neverExpiresCart = Cart::create([
            'name' => 'never_expires',
            'expires_at' => null,
        ]);

        $activeCarts = Cart::active()->get();

        expect($activeCarts)->toHaveCount(2)
            ->and($activeCarts->pluck('name')->toArray())->toEqual(['active_cart', 'never_expires']);
    });

    it('has forUser scope', function () {
        $user2 = User::create(['name' => 'User 2', 'email' => 'user2@example.com']);

        Cart::create([
            'name' => 'cart1',
            'user_id' => $this->user->id,
            'user_type' => get_class($this->user),
        ]);

        Cart::create([
            'name' => 'cart2',
            'user_id' => $user2->id,
            'user_type' => get_class($user2),
        ]);

        Cart::create(['name' => 'cart3']); // No user

        $userCarts = Cart::forUser($this->user->id)->get();

        expect($userCarts)->toHaveCount(1)
            ->and($userCarts->first()->name)->toBe('cart1');
    });

    it('has forSession scope', function () {
        Cart::create(['name' => 'cart1', 'session_id' => 'session1']);
        Cart::create(['name' => 'cart2', 'session_id' => 'session2']);
        Cart::create(['name' => 'cart3']); // No session

        $sessionCarts = Cart::forSession('session1')->get();

        expect($sessionCarts)->toHaveCount(1)
            ->and($sessionCarts->first()->name)->toBe('cart1');
    });

    it('supports soft deletes', function () {
        $cart = Cart::create(['name' => 'test_cart']);

        expect(Cart::count())->toBe(1);

        $cart->delete();

        expect(Cart::count())->toBe(0)
            ->and(Cart::withTrashed()->count())->toBe(1);
    });
});

describe('CartItem Model', function () {
    beforeEach(function () {
        $this->cart = Cart::create(['name' => 'test_cart']);
    });

    it('can create a cart item', function () {
        $cartItem = CartItem::create([
            'cart_id' => $this->cart->id,
            'cartable_id' => $this->product->id,
            'cartable_type' => get_class($this->product),
            'quantity' => 2,
            'price' => 29.99,
            'attributes' => ['size' => 'large'],
        ]);

        expect($cartItem)->toBeInstanceOf(CartItem::class)
            ->and($cartItem->cart_id)->toBe($this->cart->id)
            ->and($cartItem->cartable_id)->toBe($this->product->id)
            ->and($cartItem->cartable_type)->toBe(get_class($this->product))
            ->and($cartItem->quantity)->toBe(2)
            ->and($cartItem->price)->toBe('29.99')
            ->and($cartItem->attributes)->toBe(['size' => 'large']);
    });

    it('casts attributes to array', function () {
        $cartItem = CartItem::create([
            'cart_id' => $this->cart->id,
            'cartable_id' => $this->product->id,
            'cartable_type' => get_class($this->product),
            'attributes' => ['size' => 'large', 'color' => 'red'],
        ]);

        expect($cartItem->attributes)->toBeArray()
            ->and($cartItem->attributes)->toBe(['size' => 'large', 'color' => 'red']);
    });

    it('has cart relationship', function () {
        $cartItem = CartItem::create([
            'cart_id' => $this->cart->id,
            'cartable_id' => $this->product->id,
            'cartable_type' => get_class($this->product),
        ]);

        expect($cartItem->cart->id)->toBe($this->cart->id)
            ->and($cartItem->cart->name)->toBe($this->cart->name);
    });

    it('has cartable morph relationship', function () {
        $cartItem = CartItem::create([
            'cart_id' => $this->cart->id,
            'cartable_id' => $this->product->id,
            'cartable_type' => get_class($this->product),
        ]);

        expect($cartItem->cartable->id)->toBe($this->product->id)
            ->and($cartItem->cartable->name)->toBe($this->product->name);
    });

    it('calculates total correctly', function () {
        $cartItem = CartItem::create([
            'cart_id' => $this->cart->id,
            'cartable_id' => $this->product->id,
            'cartable_type' => get_class($this->product),
            'quantity' => 3,
            'price' => 29.99,
        ]);

        expect($cartItem->getTotal())->toBe(89.97);
    });

    it('can get unique key', function () {
        $cartItem = CartItem::create([
            'cart_id' => $this->cart->id,
            'cartable_id' => $this->product->id,
            'cartable_type' => get_class($this->product),
        ]);

        $expectedKey = md5($this->product->id.get_class($this->product));
        expect($cartItem->getUniqueKey())->toBe($expectedKey);
    });

    it('can get and set attributes', function () {
        $cartItem = CartItem::create([
            'cart_id' => $this->cart->id,
            'cartable_id' => $this->product->id,
            'cartable_type' => get_class($this->product),
            'attributes' => ['size' => 'large'],
        ]);

        expect($cartItem->getItemAttribute('size'))->toBe('large')
            ->and($cartItem->getItemAttribute('color', 'blue'))->toBe('blue');

        $cartItem->setItemAttribute('color', 'red');
        $cartItem->save();

        expect($cartItem->getItemAttribute('color'))->toBe('red')
            ->and($cartItem->attributes)->toBe(['size' => 'large', 'color' => 'red']);
    });

    it('can check if attribute exists', function () {
        $cartItem = CartItem::create([
            'cart_id' => $this->cart->id,
            'cartable_id' => $this->product->id,
            'cartable_type' => get_class($this->product),
            'attributes' => ['size' => 'large'],
        ]);

        expect($cartItem->hasItemAttribute('size'))->toBeTrue()
            ->and($cartItem->hasItemAttribute('color'))->toBeFalse();
    });

    it('has byCartable scope', function () {
        $product2 = Product::create([
            'name' => 'Product 2',
            'price' => 19.99,
            'sku' => 'TEST-002',
        ]);

        CartItem::create([
            'cart_id' => $this->cart->id,
            'cartable_id' => $this->product->id,
            'cartable_type' => get_class($this->product),
        ]);

        CartItem::create([
            'cart_id' => $this->cart->id,
            'cartable_id' => $product2->id,
            'cartable_type' => get_class($product2),
        ]);

        $productItems = CartItem::byCartable($this->product->id, get_class($this->product))->get();

        expect($productItems)->toHaveCount(1)
            ->and($productItems->first()->cartable_id)->toBe($this->product->id);
    });
});
