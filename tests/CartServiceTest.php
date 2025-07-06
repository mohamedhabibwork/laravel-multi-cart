<?php

use HCart\LaravelMultiCart\Enums\CartProvider;
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
            ->and($cart->getProvider())->toBe(CartProvider::SESSION->value);

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
        $cart = LaravelMultiCart::cart('shopping', CartProvider::SESSION->value);
        $cart->add($this->product, 2);

        $clonedCart = $cart->clone('shopping_backup', CartProvider::DATABASE->value);

        expect($clonedCart->getName())->toBe('shopping_backup')
            ->and($clonedCart->getProvider())->toBe(CartProvider::DATABASE->value)
            ->and($clonedCart->count())->toBe(2);
    });
});

describe('Cart Provider Conversion', function () {
    it('can convert cart to different provider', function () {
        $cart = LaravelMultiCart::cart('shopping', CartProvider::SESSION->value);
        $cart->add($this->product, 2);
        $cart->setConfig(['currency' => 'USD']);

        $convertedCart = $cart->convertToProvider(CartProvider::DATABASE->value);

        expect($convertedCart->getProvider())->toBe(CartProvider::DATABASE->value)
            ->and($convertedCart->getName())->toBe('shopping')
            ->and($convertedCart->count())->toBe(2)
            ->and($convertedCart->getConfig())->toBe(['currency' => 'USD']);

        // Original cart should no longer exist in session provider
        $originalExists = LaravelMultiCart::exists('shopping', CartProvider::SESSION->value);
        expect($originalExists)->toBeFalse();
    });

    it('returns same cart if converting to same provider', function () {
        $cart = LaravelMultiCart::cart('shopping', CartProvider::SESSION);
        $convertedCart = $cart->convertToProvider(CartProvider::SESSION->value);

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

describe('Cart Bulk Add and Advanced Config', function () {
    it('can add multiple items in bulk', function () {
        $cart = LaravelMultiCart::cart('bulk_cart');
        $product1 = Product::create(['name' => 'Bulk 1', 'price' => 10, 'sku' => 'BULK-1']);
        $product2 = Product::create(['name' => 'Bulk 2', 'price' => 20, 'sku' => 'BULK-2']);
        $items = [
            ['cartable' => $product1, 'quantity' => 2, 'attributes' => ['color' => 'red']],
            ['cartable' => $product2, 'quantity' => 3, 'attributes' => ['color' => 'blue']],
        ];
        $cart->addBulk($items);
        $cartItems = $cart->items();
        expect($cartItems)->toHaveCount(2)
            ->and($cart->count())->toBe(5)
            ->and($cart->quantity($product1))->toBe(2)
            ->and($cart->quantity($product2))->toBe(3);
    });

    it('applies per-item tax and shipping via interface', function () {
        $cart = LaravelMultiCart::cart('tax_shipping_cart');
        $product = new class extends Product implements \HCart\LaravelMultiCart\Contracts\ShippableInterface, \HCart\LaravelMultiCart\Contracts\TaxableInterface
        {
            public function getTaxSettings(): array
            {
                return ['type' => 'percentage', 'value' => 20.0];
            }

            public function getTaxRate(): float
            {
                return 0.2;
            }

            public function getTaxType(): string
            {
                return 'percentage';
            }

            public function isTaxIncluded(): bool
            {
                return false;
            }

            public function isCompoundTax(): bool
            {
                return false;
            }

            public function getTaxCategory(): ?string
            {
                return 'standard';
            }

            public function getShippingSettings(): array
            {
                return ['type' => 'per_piece', 'value' => 5.0, 'pieces_per_shipping' => 2, 'max_shipping_charges' => 2];
            }

            public function getShippingCost(): float
            {
                return 5.0;
            }

            public function getShippingType(): string
            {
                return 'per_piece';
            }

            public function isShippingIncluded(): bool
            {
                return false;
            }

            public function getShippingWeight(): float
            {
                return 1.0;
            }

            public function getShippingDimensions(): array
            {
                return ['length' => 1, 'width' => 1, 'height' => 1];
            }

            public function getShippingClass(): ?string
            {
                return null;
            }

            public function getShippingZones(): array
            {
                return [];
            }

            public function getPieceBasedShippingConfig(): array
            {
                return ['pieces_per_charge' => 2, 'charge_per_group' => 5.0, 'max_charges' => 2];
            }

            public function getPiecesPerShipping(): int
            {
                return 2;
            }

            public function getMaxShippingCharges(): ?int
            {
                return 2;
            }

            public function qualifiesForFreeShipping(float $cartTotal): bool
            {
                return false;
            }
        };
        $product->name = 'TaxShip';
        $product->price = 100;
        $product->sku = 'TAXSHIP-1';
        $product->save();
        $cart->add($product, 4);
        $items = $cart->items();
        expect($items)->toHaveCount(1);
        // Tax: 4*100*0.2 = 80, Shipping: 2 groups (4/2), max 2*5=10
        expect($cart->tax())->toBe(80.0)
            ->and($cart->totalShipping())->toBe(10.0);
    });

    it('applies cart-level tax and shipping via interface', function () {
        $cart = LaravelMultiCart::cart('cart_level_tax_ship');

        // Set cart-level tax and shipping via config instead of direct model manipulation
        $cart->setConfig([
            'tax' => ['type' => 'percentage', 'value' => 10.0, 'enabled' => true],
            'shipping' => ['type' => 'fixed', 'value' => 7.5, 'enabled' => true],
        ]);

        $product = Product::create(['name' => 'CartLevel', 'price' => 50, 'sku' => 'CARTLVL-1']);
        $cart->add($product, 2);

        // Cart-level tax: 2*50*0.1 = 10, shipping: 7.5
        expect($cart->tax())->toBe(10.0)
            ->and($cart->totalShipping())->toBe(7.5);
    });

    it('applies piece-based shipping config (every 2 pieces, max 3 charges)', function () {
        $cart = LaravelMultiCart::cart('piece_shipping');
        $product = new class extends Product implements \HCart\LaravelMultiCart\Contracts\ShippableInterface
        {
            public function getShippingSettings(): array
            {
                return ['type' => 'per_piece', 'pieces_per_shipping' => 2, 'max_shipping_charges' => 3, 'value' => 4.0];
            }

            public function getShippingCost(): float
            {
                return 4.0;
            }

            public function getShippingType(): string
            {
                return 'per_piece';
            }

            public function isShippingIncluded(): bool
            {
                return false;
            }

            public function getShippingWeight(): float
            {
                return 1.0;
            }

            public function getShippingDimensions(): array
            {
                return ['length' => 1, 'width' => 1, 'height' => 1];
            }

            public function getShippingClass(): ?string
            {
                return null;
            }

            public function getShippingZones(): array
            {
                return [];
            }

            public function getPieceBasedShippingConfig(): array
            {
                return ['pieces_per_charge' => 2, 'charge_per_group' => 4.0, 'max_charges' => 3];
            }

            public function getPiecesPerShipping(): int
            {
                return 2;
            }

            public function getMaxShippingCharges(): ?int
            {
                return 3;
            }

            public function qualifiesForFreeShipping(float $cartTotal): bool
            {
                return false;
            }
        };
        $product->name = 'PieceShip';
        $product->price = 10;
        $product->sku = 'PIECESHIP-1';
        $product->save();
        $cart->add($product, 7); // 7 pieces, 2 per charge, max 3 charges, 4.0 per charge
        // Charges: ceil(7/2) = 4, but max 3, so 3*4.0 = 12.0
        expect($cart->totalShipping())->toBe(12.0);
    });
});
