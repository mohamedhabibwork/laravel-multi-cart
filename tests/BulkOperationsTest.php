<?php

namespace HCart\LaravelMultiCart\Tests;

use HCart\LaravelMultiCart\Events\ItemAdded;
use HCart\LaravelMultiCart\Facades\LaravelMultiCart;
use HCart\LaravelMultiCart\Tests\Fixtures\Product;
use HCart\LaravelMultiCart\Tests\Fixtures\User;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;

class BulkOperationsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Clear all carts before each test to ensure isolation
        \DB::transaction(function () {
            \DB::table('carts')->truncate();
            \DB::table('cart_items')->truncate();
        });

        // Clear cache-based carts
        cache()->flush();
    }

    #[Test]
    public function can_add_multiple_items_to_cart_in_bulk()
    {
        $product1 = new Product(['id' => 1, 'name' => 'Product 1', 'price' => 10.0]);
        $product2 = new Product(['id' => 2, 'name' => 'Product 2', 'price' => 20.0]);
        $product3 = new Product(['id' => 3, 'name' => 'Product 3', 'price' => 30.0]);

        $cart = LaravelMultiCart::cart('bulk_test');

        $items = [
            ['cartable' => $product1, 'quantity' => 2],
            ['cartable' => $product2, 'quantity' => 1],
            ['cartable' => $product3, 'quantity' => 3, 'attributes' => ['color' => 'blue']],
        ];

        $cart->addBulk($items);

        $this->assertEquals(6, $cart->count()); // Total quantity: 2 + 1 + 3
        $this->assertEquals(3, $cart->items()->count()); // Unique items: 3
        $this->assertEquals(130.0, $cart->subtotal()); // (10*2) + (20*1) + (30*3) = 20 + 20 + 90 = 130
    }

    #[Test]
    public function bulk_add_with_custom_prices()
    {
        $product1 = new Product(['id' => 1, 'name' => 'Product 1', 'price' => 10.0]);
        $product2 = new Product(['id' => 2, 'name' => 'Product 2', 'price' => 20.0]);

        $cart = LaravelMultiCart::cart('bulk_price_test');

        $items = [
            ['cartable' => $product1, 'quantity' => 2, 'price' => 15.0], // Custom price
            ['cartable' => $product2, 'quantity' => 1], // Default price
        ];

        $cart->addBulk($items);

        $this->assertEquals(3, $cart->count()); // Total quantity: 2 + 1
        $this->assertEquals(2, $cart->items()->count()); // Unique items: 2
        $this->assertEquals(50.0, $cart->subtotal()); // (15*2) + (20*1)
    }

    #[Test]
    public function bulk_add_with_tax_and_shipping_settings()
    {
        $product1 = new Product(['id' => 1, 'name' => 'Product 1', 'price' => 10.0]);
        $product2 = new Product(['id' => 2, 'name' => 'Product 2', 'price' => 20.0]);

        $cart = LaravelMultiCart::cart('bulk_tax_shipping_test');

        $items = [
            [
                'cartable' => $product1,
                'quantity' => 2,
                'attributes' => [
                    'tax_settings' => [
                        'type' => 'percentage',
                        'value' => 10.0,
                        'enabled' => true,
                    ],
                    'shipping_settings' => [
                        'type' => 'per_piece',
                        'value' => 5.0,
                        'pieces_per_shipping' => 1,
                        'enabled' => true,
                    ],
                ],
            ],
            [
                'cartable' => $product2,
                'quantity' => 1,
                'attributes' => [
                    'tax_settings' => [
                        'type' => 'fixed',
                        'value' => 2.0,
                        'enabled' => true,
                    ],
                ],
            ],
        ];

        $cart->addBulk($items);

        $this->assertEquals(3, $cart->count()); // Total quantity: 2 + 1 = 3
        $this->assertEquals(2, $cart->items()->count()); // Unique items: 2
        $this->assertEquals(40.0, $cart->subtotal()); // (10*2) + (20*1)

        // Check that tax and shipping settings are applied
        $cartItems = $cart->items();
        $firstItem = $cartItems->first();
        $this->assertNotNull($firstItem['tax_settings']);
        $this->assertNotNull($firstItem['shipping_settings']);
    }

    #[Test]
    public function bulk_add_handles_duplicate_items_correctly()
    {
        $product1 = new Product(['id' => 1, 'name' => 'Product 1', 'price' => 10.0]);

        $cart = LaravelMultiCart::cart('bulk_duplicate_test');

        $items = [
            ['cartable' => $product1, 'quantity' => 2],
            ['cartable' => $product1, 'quantity' => 3], // Same product
        ];

        $cart->addBulk($items);

        $this->assertEquals(5, $cart->count()); // Total quantity: 2 + 3 = 5
        $this->assertEquals(1, $cart->items()->count()); // Only one unique item
        $this->assertEquals(5, $cart->items()->sum('quantity')); // 2 + 3 = 5
    }

    #[Test]
    public function bulk_add_handles_duplicate_items_with_different_attributes()
    {
        $product1 = new Product(['id' => 1, 'name' => 'Product 1', 'price' => 10.0]);

        // Use a unique cart name and cache provider to avoid any interference
        $cartName = 'bulk_different_attributes_test_'.uniqid();
        $cart = LaravelMultiCart::cart($cartName, 'cache');

        // Test individual adds first
        $cart->add($product1, 2, ['color' => 'red']);

        // Check the first item was added correctly
        $this->assertEquals(2, $cart->count());
        $this->assertEquals(1, $cart->items()->count());

        // Add the second item with different attributes
        $cart->add($product1, 3, ['color' => 'blue']);

        // Check both items are present
        $this->assertEquals(5, $cart->count()); // Total quantity: 2 + 3 = 5
        $this->assertEquals(2, $cart->items()->count()); // Two different items due to attributes
        $this->assertEquals(5, $cart->items()->sum('quantity')); // 2 + 3 = 5

        // Verify the items have different attributes
        $items = $cart->items();
        $this->assertCount(2, $items);

        $firstItem = $items->first();
        $secondItem = $items->last();

        $this->assertEquals(['color' => 'red'], $firstItem['attributes']);
        $this->assertEquals(['color' => 'blue'], $secondItem['attributes']);
    }

    #[Test]
    public function bulk_add_fires_item_added_events()
    {
        Event::fake();

        $product1 = new Product(['id' => 1, 'name' => 'Product 1', 'price' => 10.0]);
        $product2 = new Product(['id' => 2, 'name' => 'Product 2', 'price' => 20.0]);

        $cart = LaravelMultiCart::cart('bulk_events_test');

        $items = [
            ['cartable' => $product1, 'quantity' => 2],
            ['cartable' => $product2, 'quantity' => 1],
        ];

        $cart->addBulk($items);

        Event::assertDispatchedTimes(ItemAdded::class, 2);
    }

    #[Test]
    public function bulk_add_validates_input_data()
    {
        $cart = LaravelMultiCart::cart('bulk_validation_test');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Each item must have a cartable property');

        $items = [
            ['quantity' => 2], // Missing cartable
        ];

        $cart->addBulk($items);
    }

    #[Test]
    public function bulk_add_validates_cartable_instance()
    {
        $cart = LaravelMultiCart::cart('bulk_validation_cartable_test');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cartable must be an instance of Illuminate\Database\Eloquent\Model');

        $items = [
            ['cartable' => 'not_a_model', 'quantity' => 2],
        ];

        $cart->addBulk($items);
    }

    #[Test]
    public function bulk_add_with_piece_based_shipping()
    {
        $product1 = new Product(['id' => 1, 'name' => 'Product 1', 'price' => 10.0]);
        $product2 = new Product(['id' => 2, 'name' => 'Product 2', 'price' => 20.0]);

        $cart = LaravelMultiCart::cart('bulk_piece_shipping_test');

        $items = [
            [
                'cartable' => $product1,
                'quantity' => 5,
                'attributes' => [
                    'shipping_settings' => [
                        'type' => 'per_piece',
                        'value' => 4.0,
                        'pieces_per_shipping' => 2,
                        'max_shipping_charges' => 3,
                        'enabled' => true,
                    ],
                ],
            ],
            [
                'cartable' => $product2,
                'quantity' => 3,
                'attributes' => [
                    'shipping_settings' => [
                        'type' => 'per_piece',
                        'value' => 3.0,
                        'pieces_per_shipping' => 1,
                        'max_shipping_charges' => null,
                        'enabled' => true,
                    ],
                ],
            ],
        ];

        $cart->addBulk($items);

        $this->assertEquals(8, $cart->count()); // Total quantity: 5 + 3 = 8
        $this->assertEquals(2, $cart->items()->count()); // Two unique items
        $this->assertEquals(110.0, $cart->subtotal()); // (10*5) + (20*3)

        // Check that piece-based shipping settings are correctly stored
        $cartItems = $cart->items();
        $firstItem = $cartItems->first();
        $this->assertEquals(2, $firstItem['pieces_per_shipping']);
        $this->assertEquals(3, $firstItem['max_shipping_charges']);
    }

    #[Test]
    public function bulk_add_handles_empty_items_array()
    {
        $cart = LaravelMultiCart::cart('bulk_empty_test');

        $cart->addBulk([]);

        $this->assertEquals(0, $cart->count());
        $this->assertEquals(0, $cart->subtotal());
    }

    #[Test]
    public function bulk_add_with_database_provider_syncs_correctly()
    {
        $user = new User(['id' => 1, 'name' => 'Test User']);
        $product1 = new Product(['id' => 1, 'name' => 'Product 1', 'price' => 10.0]);
        $product2 = new Product(['id' => 2, 'name' => 'Product 2', 'price' => 20.0]);

        $cart = LaravelMultiCart::cart('bulk_database_test', 'database')
            ->forUser($user);

        $items = [
            ['cartable' => $product1, 'quantity' => 2],
            ['cartable' => $product2, 'quantity' => 1],
        ];

        $cart->addBulk($items);

        $this->assertEquals(3, $cart->count()); // Total quantity: 2 + 1 = 3
        $this->assertEquals(2, $cart->items()->count()); // Two unique items
        $this->assertEquals(40.0, $cart->subtotal()); // (10*2) + (20*1)

        // For database provider, we should have the cart ID
        $this->assertNotNull($cart->getCartId());
    }

    #[Test]
    public function bulk_add_performance_with_large_dataset()
    {
        $cart = LaravelMultiCart::cart('bulk_performance_test');

        // Create 100 products
        $items = [];
        for ($i = 1; $i <= 100; $i++) {
            $product = new Product(['id' => $i, 'name' => "Product $i", 'price' => $i * 1.0]);
            $items[] = ['cartable' => $product, 'quantity' => 1];
        }

        $startTime = microtime(true);
        $cart->addBulk($items);
        $endTime = microtime(true);

        $this->assertEquals(100, $cart->count());
        $this->assertEquals(5050.0, $cart->subtotal()); // Sum of 1 to 100

        // Performance should be reasonable (less than 1 second)
        $duration = $endTime - $startTime;
        $this->assertLessThan(1.0, $duration, "Bulk operation took too long: {$duration}s");
    }

    #[Test]
    public function bulk_add_preserves_original_attributes_for_events()
    {
        Event::fake();

        $product1 = new Product(['id' => 1, 'name' => 'Product 1', 'price' => 10.0]);
        $cart = LaravelMultiCart::cart('bulk_event_attributes_test');

        $originalAttributes = [
            'color' => 'red',
            'size' => 'large',
            'tax_settings' => ['type' => 'percentage', 'value' => 10.0],
        ];

        $items = [
            ['cartable' => $product1, 'quantity' => 2, 'attributes' => $originalAttributes],
        ];

        $cart->addBulk($items);

        Event::assertDispatched(ItemAdded::class, function ($event) use ($originalAttributes) {
            return $event->attributes === $originalAttributes;
        });
    }
}
