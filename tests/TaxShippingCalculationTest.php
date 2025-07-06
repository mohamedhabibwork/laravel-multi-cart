<?php

namespace HCart\LaravelMultiCart\Tests;

use HCart\LaravelMultiCart\Contracts\ShippableInterface;
use HCart\LaravelMultiCart\Contracts\TaxableInterface;
use HCart\LaravelMultiCart\Facades\LaravelMultiCart;
use HCart\LaravelMultiCart\Services\ShippingCalculator;
use HCart\LaravelMultiCart\Services\TaxCalculator;
use HCart\LaravelMultiCart\Tests\Fixtures\Product;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\Test;

class TaxShippingCalculationTest extends TestCase
{
    #[Test]
    public function piece_based_shipping_calculates_correctly()
    {
        $calculator = new ShippingCalculator;

        $settings = [
            'type' => 'per_piece',
            'value' => 5.0,
            'pieces_per_shipping' => 2,
            'max_shipping_charges' => null,
            'enabled' => true,
        ];

        // Test various quantities
        $this->assertEquals(5.0, $calculator->calculatePieceBasedShipping(1, $settings)); // 1 piece = 1 charge
        $this->assertEquals(5.0, $calculator->calculatePieceBasedShipping(2, $settings)); // 2 pieces = 1 charge
        $this->assertEquals(10.0, $calculator->calculatePieceBasedShipping(3, $settings)); // 3 pieces = 2 charges
        $this->assertEquals(10.0, $calculator->calculatePieceBasedShipping(4, $settings)); // 4 pieces = 2 charges
        $this->assertEquals(15.0, $calculator->calculatePieceBasedShipping(5, $settings)); // 5 pieces = 3 charges
    }

    #[Test]
    public function piece_based_shipping_respects_max_charges()
    {
        $calculator = new ShippingCalculator;

        $settings = [
            'type' => 'per_piece',
            'value' => 5.0,
            'pieces_per_shipping' => 2,
            'max_shipping_charges' => 3,
            'enabled' => true,
        ];

        // Test with max charges
        $this->assertEquals(5.0, $calculator->calculatePieceBasedShipping(1, $settings)); // 1 charge
        $this->assertEquals(10.0, $calculator->calculatePieceBasedShipping(3, $settings)); // 2 charges
        $this->assertEquals(15.0, $calculator->calculatePieceBasedShipping(5, $settings)); // 3 charges
        $this->assertEquals(15.0, $calculator->calculatePieceBasedShipping(7, $settings)); // 3 charges (capped)
        $this->assertEquals(15.0, $calculator->calculatePieceBasedShipping(10, $settings)); // 3 charges (capped)
    }

    #[Test]
    public function cart_applies_piece_based_shipping_to_items()
    {
        $product = new Product(['id' => 1, 'name' => 'Product 1', 'price' => 10.0]);
        $cart = LaravelMultiCart::cart('piece_shipping_test');

        $cart->add($product, 5, [
            'shipping_settings' => [
                'type' => 'per_piece',
                'value' => 4.0,
                'pieces_per_shipping' => 2,
                'max_shipping_charges' => 3,
                'enabled' => true,
            ],
        ]);

        $this->assertEquals(5, $cart->count()); // Total quantity: 5 items
        $this->assertEquals(1, $cart->items()->count()); // Unique items: 1 product
        $this->assertEquals(50.0, $cart->subtotal()); // 10 * 5

        // Calculate expected shipping: ceil(5/2) = 3 charges, but max 3, so 3 * 4.0 = 12.0
        $shipping = $cart->totalShipping();
        $this->assertEquals(12.0, $shipping);
    }

    #[Test]
    public function tax_calculator_handles_compound_tax()
    {
        $calculator = new TaxCalculator;

        // Create a mock CartItem for testing
        $cartItem = new class extends \HCart\LaravelMultiCart\Models\CartItem
        {
            public $price = 100.0;

            public $quantity = 1;

            public $tax_settings = [
                'type' => 'percentage',
                'value' => 10.0,
                'enabled' => true,
            ];
        };

        $discountAmount = 10.0;
        $taxSettings = [
            'type' => 'percentage',
            'value' => 10.0,
            'enabled' => true,
        ];

        $compoundTax = $calculator->calculateCompoundTax($cartItem, $discountAmount, $taxSettings);
        $this->assertEquals(9.0, $compoundTax); // (100 - 10) * 0.10 = 9.0
    }

    #[Test]
    public function cart_applies_item_level_tax_and_shipping()
    {
        $product = new TaxableShippableProduct(['id' => 1, 'name' => 'Taxable Product', 'price' => 20.0]);
        $cart = LaravelMultiCart::cart('item_tax_shipping_test');

        $cart->add($product, 2);

        $this->assertEquals(2, $cart->count()); // Total quantity
        $this->assertEquals(1, $cart->items()->count()); // Unique items
        $this->assertEquals(40.0, $cart->subtotal()); // 20 * 2

        // Tax: 40 * 0.08 = 3.2
        $tax = $cart->totalTax();
        $this->assertEquals(3.2, $tax);

        // Shipping: per_piece with 2 pieces, 2 pieces per shipping = 1 charge * 5.0 = 5.0
        $shipping = $cart->totalShipping();
        $this->assertEquals(5.0, $shipping);
    }

    #[Test]
    public function shipping_calculator_handles_weight_based_shipping()
    {
        $calculator = new ShippingCalculator;

        $cartItems = [
            ['attributes' => ['weight' => 2.0], 'quantity' => 1],
            ['attributes' => ['weight' => 1.5], 'quantity' => 2],
        ];

        $settings = [
            'type' => 'weight_based',
            'base_rate' => 5.0,
            'weight_rate' => 2.0,
            'enabled' => true,
        ];

        // Total weight: (2.0 * 1) + (1.5 * 2) = 5.0
        // Shipping: 5.0 + (5.0 * 2.0) = 15.0
        $shipping = $calculator->calculateCartShipping(100.0, $settings, $cartItems);
        $this->assertEquals(15.0, $shipping);
    }

    #[Test]
    public function free_shipping_threshold_works()
    {
        $calculator = new ShippingCalculator;

        $settings = [
            'type' => 'fixed',
            'value' => 10.0,
            'free_shipping_threshold' => 100.0,
            'enabled' => true,
        ];

        // Below threshold
        $shipping = $calculator->calculateCartShipping(50.0, $settings, []);
        $this->assertEquals(10.0, $shipping);

        // At threshold
        $shipping = $calculator->calculateCartShipping(100.0, $settings, []);
        $this->assertEquals(0.0, $shipping);

        // Above threshold
        $shipping = $calculator->calculateCartShipping(150.0, $settings, []);
        $this->assertEquals(0.0, $shipping);
    }

    #[Test]
    public function cart_calculates_total_with_tax_and_shipping()
    {
        $product = new Product(['id' => 1, 'name' => 'Product 1', 'price' => 50.0]);
        $cart = LaravelMultiCart::cart('total_calculation_test');

        $cart->add($product, 2, [
            'tax_settings' => [
                'type' => 'percentage',
                'value' => 10.0,
                'enabled' => true,
            ],
            'shipping_settings' => [
                'type' => 'fixed',
                'value' => 15.0,
                'enabled' => true,
            ],
        ]);

        $subtotal = $cart->subtotal(); // 50 * 2 = 100
        $tax = $cart->totalTax(); // 100 * 0.10 = 10
        $shipping = $cart->totalShipping(); // 15 * 2 (per item) = 30
        $total = $cart->total(); // 100 + 10 + 30 = 140

        $this->assertEquals(100.0, $subtotal);
        $this->assertEquals(10.0, $tax);
        $this->assertEquals(30.0, $shipping);
        $this->assertEquals(140.0, $total);
    }

    #[Test]
    public function cart_handles_multiple_items_with_different_tax_shipping()
    {
        $product1 = new Product(['id' => 1, 'name' => 'Product 1', 'price' => 20.0]);
        $product2 = new Product(['id' => 2, 'name' => 'Product 2', 'price' => 30.0]);
        $cart = LaravelMultiCart::cart('multiple_items_test');

        $cart->add($product1, 1, [
            'tax_settings' => [
                'type' => 'percentage',
                'value' => 8.0,
                'enabled' => true,
            ],
            'shipping_settings' => [
                'type' => 'fixed',
                'value' => 5.0,
                'enabled' => true,
            ],
        ]);

        $cart->add($product2, 2, [
            'tax_settings' => [
                'type' => 'fixed',
                'value' => 3.0,
                'enabled' => true,
            ],
            'shipping_settings' => [
                'type' => 'per_piece',
                'value' => 4.0,
                'pieces_per_shipping' => 1,
                'enabled' => true,
            ],
        ]);

        $subtotal = $cart->subtotal(); // (20 * 1) + (30 * 2) = 80
        $tax = $cart->totalTax(); // (20 * 0.08) + (3 * 2) = 1.6 + 6 = 7.6
        $shipping = $cart->totalShipping(); // 5 + (4 * 2) = 13

        $this->assertEquals(80.0, $subtotal);
        $this->assertEquals(7.6, $tax);
        $this->assertEquals(13.0, $shipping);
    }

    #[Test]
    public function cart_with_disabled_tax_shipping_calculates_correctly()
    {
        $product = new Product(['id' => 1, 'name' => 'Product 1', 'price' => 25.0]);
        $cart = LaravelMultiCart::cart('disabled_tax_shipping_test');

        $cart->add($product, 1, [
            'tax_settings' => [
                'type' => 'percentage',
                'value' => 10.0,
                'enabled' => false, // Disabled
            ],
            'shipping_settings' => [
                'type' => 'fixed',
                'value' => 8.0,
                'enabled' => false, // Disabled
            ],
        ]);

        $this->assertEquals(25.0, $cart->subtotal());
        $this->assertEquals(0.0, $cart->totalTax());
        $this->assertEquals(0.0, $cart->totalShipping());
        $this->assertEquals(25.0, $cart->total());
    }

    #[Test]
    public function piece_based_shipping_with_zero_pieces_per_shipping()
    {
        $calculator = new ShippingCalculator;

        $settings = [
            'type' => 'per_piece',
            'value' => 5.0,
            'pieces_per_shipping' => 0, // Invalid configuration
            'enabled' => true,
        ];

        $shipping = $calculator->calculatePieceBasedShipping(5, $settings);
        $this->assertEquals(0.0, $shipping); // Should return 0 for invalid config
    }

    #[Test]
    public function tax_validation_works_correctly()
    {
        $calculator = new TaxCalculator;

        $validSettings = [
            'type' => 'percentage',
            'value' => 10.0,
            'enabled' => true,
            'included' => false,
            'compound' => false,
        ];

        $invalidTypeSettings = [
            'type' => 'invalid_type',
            'value' => 10.0,
        ];

        $invalidValueSettings = [
            'type' => 'percentage',
            'value' => 'not_numeric',
        ];

        $this->assertTrue($calculator->validateTaxSettings($validSettings));
        $this->assertFalse($calculator->validateTaxSettings($invalidTypeSettings));
        $this->assertFalse($calculator->validateTaxSettings($invalidValueSettings));
    }

    #[Test]
    public function shipping_validation_works_correctly()
    {
        $calculator = new ShippingCalculator;

        $validSettings = [
            'type' => 'per_piece',
            'value' => 5.0,
            'enabled' => true,
            'pieces_per_shipping' => 2,
            'max_shipping_charges' => 3,
        ];

        $invalidTypeSettings = [
            'type' => 'invalid_type',
            'value' => 5.0,
        ];

        $invalidPiecesSettings = [
            'type' => 'per_piece',
            'value' => 5.0,
            'pieces_per_shipping' => -1,
        ];

        $this->assertTrue($calculator->validateShippingSettings($validSettings));
        $this->assertFalse($calculator->validateShippingSettings($invalidTypeSettings));
        $this->assertFalse($calculator->validateShippingSettings($invalidPiecesSettings));
    }
}

// Test fixture class implementing both TaxableInterface and ShippableInterface
class TaxableShippableProduct extends Model implements ShippableInterface, TaxableInterface
{
    use \HCart\LaravelMultiCart\Traits\Cartable;

    protected $fillable = ['id', 'name', 'price'];

    public function getCartPrice(): float
    {
        return (float) $this->price;
    }

    // TaxableInterface implementation
    public function getTaxSettings(): array
    {
        return [
            'type' => 'percentage',
            'value' => 8.0,
            'enabled' => true,
            'included' => false,
            'compound' => false,
        ];
    }

    public function getTaxRate(): float
    {
        return 8.0;
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

    // ShippableInterface implementation
    public function getShippingSettings(): array
    {
        return [
            'type' => 'per_piece',
            'value' => 5.0,
            'enabled' => true,
            'pieces_per_shipping' => 2,
            'max_shipping_charges' => null,
        ];
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
        return ['length' => 10, 'width' => 8, 'height' => 5];
    }

    public function getShippingClass(): ?string
    {
        return 'standard';
    }

    public function getShippingZones(): array
    {
        return ['US', 'CA'];
    }

    public function getPieceBasedShippingConfig(): array
    {
        return [
            'pieces_per_charge' => 2,
            'charge_per_group' => 5.0,
            'max_charges' => null,
        ];
    }

    public function getPiecesPerShipping(): int
    {
        return 2;
    }

    public function getMaxShippingCharges(): ?int
    {
        return null;
    }

    public function qualifiesForFreeShipping(float $cartTotal): bool
    {
        return $cartTotal >= 100.0;
    }
}
