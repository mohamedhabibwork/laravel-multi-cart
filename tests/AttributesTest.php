<?php

namespace HCart\LaravelMultiCart\Tests;

use HCart\LaravelMultiCart\Attributes\AttributeReader;
use HCart\LaravelMultiCart\Attributes\ShippingConfiguration;
use HCart\LaravelMultiCart\Attributes\TaxConfiguration;
use HCart\LaravelMultiCart\Facades\LaravelMultiCart;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\Test;

class AttributesTest extends TestCase
{
    #[Test]
    public function tax_configuration_can_be_created_from_array()
    {
        $data = [
            'rate' => 0.08,
            'type' => 'percentage',
            'included' => true,
            'compound' => false,
            'category' => 'standard',
            'exemptions' => ['tax_exempt_category'],
            'description' => 'Standard tax rate',
        ];

        $config = TaxConfiguration::fromArray($data);

        $this->assertEquals(0.08, $config->rate);
        $this->assertEquals('percentage', $config->type);
        $this->assertTrue($config->included);
        $this->assertFalse($config->compound);
        $this->assertEquals('standard', $config->category);
        $this->assertEquals(['tax_exempt_category'], $config->exemptions);
        $this->assertEquals('Standard tax rate', $config->description);
    }

    #[Test]
    public function tax_configuration_can_be_converted_to_array()
    {
        $config = new TaxConfiguration(
            rate: 0.1,
            type: 'percentage',
            included: false,
            compound: true,
            category: 'luxury',
            exemptions: ['non_profit'],
            description: 'Luxury tax'
        );

        $array = $config->toArray();

        $this->assertEquals([
            'rate' => 0.1,
            'type' => 'percentage',
            'included' => false,
            'compound' => true,
            'category' => 'luxury',
            'exemptions' => ['non_profit'],
            'description' => 'Luxury tax',
        ], $array);
    }

    #[Test]
    public function shipping_configuration_can_be_created_from_array()
    {
        $data = [
            'cost' => 5.99,
            'type' => 'per_piece',
            'included' => false,
            'weight' => 0.5,
            'dimensions' => ['length' => 10, 'width' => 5, 'height' => 2],
            'class' => 'standard',
            'zones' => ['US', 'CA'],
            'pieces_per_shipping' => 2,
            'max_shipping_charges' => 3,
            'free_shipping_threshold' => 100.0,
            'exemptions' => ['free_shipping_category'],
            'description' => 'Standard shipping',
        ];

        $config = ShippingConfiguration::fromArray($data);

        $this->assertEquals(5.99, $config->cost);
        $this->assertEquals('per_piece', $config->type);
        $this->assertFalse($config->included);
        $this->assertEquals(0.5, $config->weight);
        $this->assertEquals(['length' => 10, 'width' => 5, 'height' => 2], $config->dimensions);
        $this->assertEquals('standard', $config->class);
        $this->assertEquals(['US', 'CA'], $config->zones);
        $this->assertEquals(2, $config->piecesPerShipping);
        $this->assertEquals(3, $config->maxShippingCharges);
        $this->assertEquals(100.0, $config->freeShippingThreshold);
        $this->assertEquals(['free_shipping_category'], $config->exemptions);
        $this->assertEquals('Standard shipping', $config->description);
    }

    #[Test]
    public function shipping_configuration_can_calculate_piece_based_shipping()
    {
        $config = new ShippingConfiguration(
            cost: 5.0,
            type: 'per_piece',
            piecesPerShipping: 2,
            maxShippingCharges: 3
        );

        // Test piece-based calculation
        $this->assertEquals(5.0, $config->calculatePieceBasedShipping(1)); // 1 piece = 1 charge
        $this->assertEquals(5.0, $config->calculatePieceBasedShipping(2)); // 2 pieces = 1 charge
        $this->assertEquals(10.0, $config->calculatePieceBasedShipping(3)); // 3 pieces = 2 charges
        $this->assertEquals(10.0, $config->calculatePieceBasedShipping(4)); // 4 pieces = 2 charges
        $this->assertEquals(15.0, $config->calculatePieceBasedShipping(5)); // 5 pieces = 3 charges
        $this->assertEquals(15.0, $config->calculatePieceBasedShipping(6)); // 6 pieces = 3 charges
        $this->assertEquals(15.0, $config->calculatePieceBasedShipping(7)); // 7 pieces = 3 charges (capped)
    }

    #[Test]
    public function attribute_reader_can_read_tax_configuration_from_class()
    {
        $product = new ProductWithTaxAttribute;
        $taxConfig = AttributeReader::getTaxConfiguration($product);

        $this->assertInstanceOf(TaxConfiguration::class, $taxConfig);
        $this->assertEquals(0.08, $taxConfig->rate);
        $this->assertEquals('percentage', $taxConfig->type);
    }

    #[Test]
    public function attribute_reader_can_read_shipping_configuration_from_class()
    {
        $product = new ProductWithShippingAttribute;
        $shippingConfig = AttributeReader::getShippingConfiguration($product);

        $this->assertInstanceOf(ShippingConfiguration::class, $shippingConfig);
        $this->assertEquals(5.99, $shippingConfig->cost);
        $this->assertEquals('per_piece', $shippingConfig->type);
        $this->assertEquals(2, $shippingConfig->piecesPerShipping);
    }

    #[Test]
    public function attribute_reader_can_read_tax_configuration_from_method()
    {
        $product = new ProductWithTaxMethodAttribute;
        $taxConfig = AttributeReader::getTaxConfiguration($product);

        $this->assertInstanceOf(TaxConfiguration::class, $taxConfig);
        $this->assertEquals(0.15, $taxConfig->rate);
        $this->assertEquals('percentage', $taxConfig->type);
    }

    #[Test]
    public function attribute_reader_returns_all_configurations()
    {
        $product = new ProductWithMultipleAttributes;
        $taxConfigs = AttributeReader::getAllTaxConfigurations($product);
        $shippingConfigs = AttributeReader::getAllShippingConfigurations($product);

        $this->assertArrayHasKey('class', $taxConfigs);
        $this->assertArrayHasKey('method_getTaxRate', $taxConfigs);
        $this->assertArrayHasKey('class', $shippingConfigs);
        $this->assertArrayHasKey('method_getShippingCost', $shippingConfigs);
    }

    #[Test]
    public function cart_service_resolves_tax_settings_from_attributes()
    {
        $product = new ProductWithTaxAttribute;
        $cart = LaravelMultiCart::cart('test_cart');

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($cart);
        $method = $reflection->getMethod('resolveTaxSettings');
        $method->setAccessible(true);

        $taxSettings = $method->invoke($cart, $product, []);

        $this->assertEquals('percentage', $taxSettings['type']);
        $this->assertEquals(0.08, $taxSettings['value']);
        $this->assertFalse($taxSettings['included']);
        $this->assertFalse($taxSettings['compound']);
    }

    #[Test]
    public function cart_service_resolves_shipping_settings_from_attributes()
    {
        $product = new ProductWithShippingAttribute;
        $cart = LaravelMultiCart::cart('test_cart');

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($cart);
        $method = $reflection->getMethod('resolveShippingSettings');
        $method->setAccessible(true);

        $shippingSettings = $method->invoke($cart, $product, []);

        $this->assertEquals('per_piece', $shippingSettings['type']);
        $this->assertEquals(5.99, $shippingSettings['value']);
        $this->assertEquals(2, $shippingSettings['pieces_per_shipping']);
        $this->assertEquals(3, $shippingSettings['max_shipping_charges']);
    }

    #[Test]
    public function explicit_attributes_override_php_attributes()
    {
        $product = new ProductWithTaxAttribute;
        $cart = LaravelMultiCart::cart('test_cart');

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($cart);
        $method = $reflection->getMethod('resolveTaxSettings');
        $method->setAccessible(true);

        $explicitTaxSettings = ['type' => 'fixed', 'value' => 10.0];
        $taxSettings = $method->invoke($cart, $product, ['tax_settings' => $explicitTaxSettings]);

        $this->assertEquals('fixed', $taxSettings['type']);
        $this->assertEquals(10.0, $taxSettings['value']);
    }
}

// Test fixture classes with attributes
#[TaxConfiguration(rate: 0.08, type: 'percentage', included: false, compound: false)]
class ProductWithTaxAttribute extends Model
{
    use \HCart\LaravelMultiCart\Traits\Cartable;

    public function getCartPrice(): float
    {
        return 10.0;
    }
}

#[ShippingConfiguration(cost: 5.99, type: 'per_piece', piecesPerShipping: 2, maxShippingCharges: 3)]
class ProductWithShippingAttribute extends Model
{
    use \HCart\LaravelMultiCart\Traits\Cartable;

    public function getCartPrice(): float
    {
        return 15.0;
    }
}

class ProductWithTaxMethodAttribute extends Model
{
    use \HCart\LaravelMultiCart\Traits\Cartable;

    #[TaxConfiguration(rate: 0.15, type: 'percentage', included: false, compound: true)]
    public function getTaxRate(): float
    {
        return 0.15;
    }

    public function getCartPrice(): float
    {
        return 20.0;
    }
}

#[TaxConfiguration(rate: 0.12, type: 'percentage')]
#[ShippingConfiguration(cost: 8.99, type: 'fixed')]
class ProductWithMultipleAttributes extends Model
{
    use \HCart\LaravelMultiCart\Traits\Cartable;

    #[TaxConfiguration(rate: 0.18, type: 'percentage')]
    public function getTaxRate(): float
    {
        return 0.18;
    }

    #[ShippingConfiguration(cost: 12.99, type: 'per_piece')]
    public function getShippingCost(): float
    {
        return 12.99;
    }

    public function getCartPrice(): float
    {
        return 25.0;
    }
}
