# Laravel Multi-Cart Package

[![Latest Version on Packagist](https://img.shields.io/packagist/v/hcart/laravel-multi-cart.svg?style=flat-square)](https://packagist.org/packages/hcart/laravel-multi-cart)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/mohamedhabibwork/laravel-multi-cart/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/mohamedhabibwork/laravel-multi-cart/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/mohamedhabibwork/laravel-multi-cart/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/mohamedhabibwork/laravel-multi-cart/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/hcart/laravel-multi-cart.svg?style=flat-square)](https://packagist.org/packages/hcart/laravel-multi-cart)

A flexible, extensible shopping cart solution for Laravel applications that supports multiple cart instances with configurable storage providers. Built for Laravel v11+ and PHP 8.2+, it leverages modern Laravel features including polymorphic relationships and JSON configuration storage.

## Table of Contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Basic Usage](#basic-usage)
- [User Integration](#user-integration)
- [Cartable Items](#cartable-items)
- [CartProvider Enum](#cartprovider-enum)
- [Storage Providers](#storage-providers)
- [Enhanced Provider Conversion](#enhanced-provider-conversion)
- [Advanced Configuration](#advanced-configuration)
- [Events](#events)
- [Commands](#commands)
- [API Reference](#api-reference)
- [Examples](#examples)
- [Testing](#testing)
- [Changelog](#changelog)
- [Contributing](#contributing)
- [Security Vulnerabilities](#security-vulnerabilities)
- [Credits](#credits)
- [License](#license)

## Features

- **Multiple Cart Instances**: Create and manage multiple named cart instances for different purposes (shopping, wishlist, favorites, etc.)
- **CartProvider Enum**: Type-safe provider selection with `CartProvider::SESSION`, `CartProvider::DATABASE`, etc.
- **Configurable Storage Providers**: Choose from session, cache, database, Redis, or file storage providers
- **Enhanced Provider Conversion**: Advanced `convertToProvider()` with automatic user association and cart merging
- **Polymorphic Relationships**: Add any Eloquent model to carts with full relationship support
- **JSON Configuration Storage**: Flexible configuration with JSON/JSONB support for enhanced flexibility
- **Soft Delete Support**: Built-in soft delete functionality with `deleted_at` timestamps for data recovery
- **Type-Safe Implementation**: Modern PHP 8.2+ features with strict typing and comprehensive interfaces
- **Comprehensive Event System**: Listen to cart creation, updates, deletions, and item changes
- **Built-in Validation**: Automatic validation and error handling with custom exceptions
- **Trait Support**: Easy integration with User models and cartable items using Laravel traits
- **Automatic Cleanup**: Scheduled cleanup of expired carts with configurable retention policies
- **Custom Callbacks**: Extensible callback system for item uniqueness, updates, and removals
- **Provider Migration**: Seamless migration between different storage providers with merging support
- **Performance Optimized**: Efficient caching strategies and optimized database queries

## Requirements

- **Laravel Framework**: v11.0+
- **PHP Version**: 8.2+
- **Database**: MySQL 8.0+, PostgreSQL 13+, SQLite 3.35+
- **Cache Drivers**: Redis, Memcached, File, Database
- **Session Drivers**: File, Cookie, Database, APC, Memcached, Redis

## Installation

### Step 1: Install via Composer

```bash
composer require hcart/laravel-multi-cart
```

### Step 2: Publish Configuration

```bash
php artisan vendor:publish --tag="laravel-multi-cart-config"
```

### Step 3: Database Setup (Optional)

If you plan to use the database provider, publish and run the migrations:

```bash
php artisan cart:publish-migrations
php artisan migrate
```

### Step 4: Environment Configuration

Add these environment variables to your `.env` file:

```env
LARAVEL_MULTI_CART_PROVIDER=session
LARAVEL_MULTI_CART_TAX_RATE=0.08
LARAVEL_MULTI_CART_CURRENCY=USD
```

## Configuration

The configuration file `config/laravel-multi-cart.php` allows extensive customization:

```php
<?php

return [
    // Default storage provider
    'default' => env('LARAVEL_MULTI_CART_PROVIDER', 'session'),
    
    // Custom models (you can extend these)
    'models' => [
        'cart' => \HCart\LaravelMultiCart\Models\Cart::class,
        'cart_item' => \HCart\LaravelMultiCart\Models\CartItem::class,
    ],
    
    // Configuration class
    'config_class' => \HCart\LaravelMultiCart\Config\LaravelMultiCartConfig::class,
    
    // Behavioral callbacks
    'callbacks' => [
        // Custom item uniqueness logic
        'unique_item' => null, 
        // Callback when item is updated
        'item_update' => null, 
        // Callback when item is removed
        'item_remove' => null, 
    ],
    
    // Cart behavior settings
    'prevent_duplicates' => true,
    'tax_rate' => env('LARAVEL_MULTI_CART_TAX_RATE', 0.0),
    'currency' => env('LARAVEL_MULTI_CART_CURRENCY', 'USD'),
    
    // Storage provider configurations
    'providers' => [
        'session' => [
            'driver' => 'session',
            'prefix' => 'laravel_multi_cart_'
        ],
        
        'cache' => [
            'driver' => 'cache',
            'store' => env('CACHE_DRIVER', 'file'),
            'prefix' => 'laravel_multi_cart_',
            'ttl' => 3600
        ],
        
        'database' => [
            'driver' => 'database',
            'connection' => env('DB_CONNECTION', 'mysql'),
            'soft_deletes' => true
        ],
        
        'redis' => [
            'driver' => 'redis',
            'connection' => env('REDIS_CONNECTION', 'default'),
            'prefix' => 'laravel_multi_cart_',
            'ttl' => 3600
        ],
        
        'file' => [
            'driver' => 'file',
            'path' => storage_path('laravel_multi_cart'),
            'ttl' => 3600
        ]
    ],
    
    // Automatic cleanup settings
    'cleanup' => [
        'enabled' => true,
        'schedule' => 'daily',
        'expire_after' => 168 // hours (7 days)
    ]
];
```

## Basic Usage

### Creating and Managing Carts

```php
use HCart\LaravelMultiCart\Enums\CartProvider;
use HCart\LaravelMultiCart\Facades\LaravelMultiCart;

// Get or create a cart (defaults to session)
$cart = LaravelMultiCart::cart('shopping');

// Create cart with specific provider using enum
$cart = LaravelMultiCart::cart('wishlist', CartProvider::DATABASE);

// Create cart with custom configuration using enum
$cart = LaravelMultiCart::create('premium', [
    'tax_rate' => 0.15,
    'currency' => 'EUR'
], CartProvider::DATABASE);

// Check if cart exists
if (LaravelMultiCart::exists('shopping', CartProvider::SESSION)) {
    // Cart exists
}
```

### Adding Items to Cart

```php
// Basic item addition
$product = Product::find(1);
$cart->add($product, 2); // Add 2 quantities

// Add with custom price
$cart->add($product, 1, [], 19.99);

// Add with attributes
$cart->add($product, 1, [
    'size' => 'large',
    'color' => 'red',
    'gift_wrap' => true
]);

// Check if item exists in cart
if ($cart->has($product)) {
    // Product is in cart
}

// Get quantity of specific item
$quantity = $cart->quantity($product);
```

### Cart Operations

```php
// Get cart information
$itemCount = $cart->count();
$subtotal = $cart->subtotal();
$tax = $cart->tax();
$total = $cart->total();

// Get all items
$items = $cart->items();

// Get specific item
$item = $cart->get($itemId);

// Update item
$cart->update($itemId, [
    'quantity' => 5,
    'price' => 24.99,
    'attributes' => ['color' => 'blue']
]);

// Remove item
$cart->remove($itemId);

// Clear all items
$cart->clear();

// Clone cart
$clonedCart = $cart->clone('shopping_backup');

// Clone to different provider
$dbCart = $cart->clone('shopping_db', 'database');

// Convert cart to different provider
$convertedCart = $cart->convertToProvider('redis');

// Delete cart
$cart->delete();
```

## User Integration

### Adding HasCarts Trait

Add the `HasCarts` trait to your User model for seamless user-cart integration:

```php
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use HCart\LaravelMultiCart\Enums\CartProvider;
use HCart\LaravelMultiCart\Traits\HasCarts;

class User extends Authenticatable
{
    use HasCarts;
    
    // ... other model code
}
```

### User Cart Operations

```php
$user = auth()->user();

// Create user-specific cart
$cart = $user->createCart('shopping', ['currency' => 'EUR']);

// Get user's cart (uses default provider)
$cart = $user->getCart('shopping');

// Get cart with specific provider using enum
$cart = $user->getCart('wishlist', CartProvider::DATABASE);

// Check if user has cart
if ($user->hasCart('favorites')) {
    $favorites = $user->getCart('favorites');
}

// Get all user cart names
$cartNames = $user->getCartNames();

// Clone user's cart with enum support
$clonedCart = $user->cloneCart('shopping', 'shopping_backup', CartProvider::DATABASE);

// Convert cart to different provider using enum
$convertedCart = $user->convertCartToProvider('shopping', CartProvider::DATABASE->value);

// Enhanced user cart conversion to database with merging
$mergedCart = $user->convertCartToDatabase('session_cart', [
    'merge_with_existing' => true,
    'target_cart_name' => 'shopping',
]);

// Delete user cart
$user->deleteCart('shopping');

// Get user's carts relationship
$userCarts = $user->carts; // Returns Eloquent collection
```

## Cartable Items

### Adding Cartable Trait

Add the `Cartable` trait to models that can be added to carts:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use HCart\LaravelMultiCart\Traits\Cartable;

class Product extends Model
{
    use Cartable;
    
    // Required method for cart integration
    public function getCartPrice(): float
    {
        return (float) $this->price;
    }
    
    // Optional: Custom cart name
    public function getCartName(): string
    {
        return $this->name;
    }
    
    // Optional: Additional attributes for cart
    public function getCartAttributes(): array
    {
        return [
            'sku' => $this->sku,
            'weight' => $this->weight,
        ];
    }
}
```

### Cartable Operations

```php
$product = Product::find(1);

// Check if product is in any cart
if ($product->isInCart()) {
    // Product is in at least one cart
}

// Check if product is in specific cart
if ($product->isInCart('wishlist')) {
    // Product is in wishlist
}

// Get total quantity across all carts
$totalQuantity = $product->getCartQuantity();

// Get quantity in specific cart
$wishlistQuantity = $product->getCartQuantity('wishlist');

// Remove from all carts
$product->removeFromCart();

// Remove from specific cart
$product->removeFromCart('shopping');

// Get cart items relationship
$cartItems = $product->cartItems; // Returns Eloquent collection
```

## CartProvider Enum

The CartProvider enum provides type-safe provider selection and enhanced functionality:

```php
use HCart\LaravelMultiCart\Enums\CartProvider;

// Available provider constants
CartProvider::SESSION     // 'session' - Best for guest users
CartProvider::CACHE       // 'cache' - High performance scenarios  
CartProvider::DATABASE    // 'database' - Persistent user carts
CartProvider::REDIS       // 'redis' - Distributed applications
CartProvider::FILE        // 'file' - Simple file-based storage

// Get all available providers
$providers = CartProvider::getAll();
// Returns: ['session', 'cache', 'database', 'redis', 'file']

// Check provider capabilities
$databaseProvider = CartProvider::DATABASE;
echo $databaseProvider->getDisplayName();    // "Database"
echo $databaseProvider->getDescription();    // "Persistent storage in database"

// Provider capability checks
if ($databaseProvider->isStateful()) {
    // Provider supports user associations and persistence
}

if ($databaseProvider->supportsMerging()) {
    // Provider supports cart merging operations
}

// Create provider from string
$provider = CartProvider::fromString('database');

// Using enum in operations
$cart = LaravelMultiCart::cart('shopping', CartProvider::DATABASE);
$convertedCart = $cart->convertToProvider(CartProvider::REDIS);

// Manager methods with enum support
$manager = app(\HCart\LaravelMultiCart\Services\CartManager::class);
$providerInfo = $manager->getProviderInfo(CartProvider::DATABASE);
```

## Storage Providers

### Session Provider

Best for guest users and temporary carts:

```php
$cart = LaravelMultiCart::cart('guest_cart', CartProvider::SESSION);
```

### Database Provider

Best for persistent user carts with relationships:

```php
$cart = LaravelMultiCart::cart('user_cart', CartProvider::DATABASE);

// Supports user associations
$cart->forUser($user);

// Get cart ID for relationships
$cartId = $cart->getCartId();
```

### Cache Provider

Best for high-performance scenarios:

```php
$cart = LaravelMultiCart::cart('fast_cart', CartProvider::CACHE);
```

### Redis Provider

Best for distributed applications:

```php
$cart = LaravelMultiCart::cart('distributed_cart', CartProvider::REDIS);
```

### File Provider

Best for simple applications without database/cache:

```php
$cart = LaravelMultiCart::cart('file_cart', CartProvider::FILE);
```

## Enhanced Provider Conversion

The enhanced `convertToProvider()` method supports automatic user association and cart merging:

```php
use HCart\LaravelMultiCart\Enums\CartProvider;

// Basic provider conversion
$cart = LaravelMultiCart::cart('shopping', CartProvider::SESSION);
$cart->add($product, 2);

// Convert to database (automatically associates with authenticated user)
$dbCart = $cart->convertToProvider(CartProvider::DATABASE);

// Enhanced conversion with options
$options = [
    'user_id' => auth()->id(),                    // Force specific user association
    'user_type' => User::class,                   // Specify user model type  
    'merge_with_existing' => true,                // Merge with existing cart
    'target_cart_name' => 'existing_cart',       // Specific cart to merge with
    'merge_strategy' => 'combine_quantities',     // How to handle duplicates
    'preserve_attributes' => true,                // Keep item attributes
];

$convertedCart = $cart->convertToProvider(CartProvider::DATABASE, $options);

// Guest to user migration example
$guestCart = LaravelMultiCart::cart('guest_shopping', CartProvider::SESSION);
$guestCart->add($product, 1);

// User logs in - convert and merge automatically
$userCart = $guestCart->convertToProvider(CartProvider::DATABASE, [
    'user_id' => auth()->id(),
    'merge_with_existing' => true,
    'target_cart_name' => 'shopping',
]);

// Get available carts for merging (database provider only)
$availableCarts = $cart->getAvailableCartsForMerging();
// Returns: [['name' => 'shopping', 'item_count' => 3], ...]

// Check if provider supports merging before attempting
if (CartProvider::DATABASE->supportsMerging()) {
    $converted = $cart->convertToProvider(CartProvider::DATABASE, [
        'merge_with_existing' => true,
    ]);
}

// User trait methods with enhanced conversion
$user = auth()->user();
$userCart = $user->convertCartToDatabase('guest_cart', [
    'merge_with_existing' => true,
    'target_cart_name' => 'shopping',
]);
```

## Advanced Configuration

### Custom Unique Item Callbacks

Define how items are considered unique in carts:

```php
use HCart\LaravelMultiCart\Config\LaravelMultiCartConfig;

$config = new LaravelMultiCartConfig([
    'callbacks' => [
        'unique_item' => function ($cartableId, $cartableType, $attributes) {
            // Include size and color in uniqueness
            $uniqueKey = $cartableId . $cartableType;
            if (isset($attributes['size'])) {
                $uniqueKey .= $attributes['size'];
            }
            if (isset($attributes['color'])) {
                $uniqueKey .= $attributes['color'];
            }
            return md5($uniqueKey);
        },
    ],
]);

// Apply configuration to specific cart
$cart = LaravelMultiCart::cart('custom_cart')->withConfig($config);

// Set global configuration
LaravelMultiCart::setConfig($config);
```

### Item Update and Remove Callbacks

```php
$config = new LaravelMultiCartConfig([
    'callbacks' => [
        'item_update' => function ($cartItem, $oldData, $newData) {
            // Log item updates
            Log::info('Cart item updated', [
                'cart_id' => $cartItem['cart_id'] ?? null,
                'item_id' => $cartItem['id'],
                'old_data' => $oldData,
                'new_data' => $newData
            ]);
        },
        
        'item_remove' => function ($cartItem) {
            // Log item removals
            Log::info('Cart item removed', [
                'cart_id' => $cartItem['cart_id'] ?? null,
                'item_id' => $cartItem['id'],
                'cartable_type' => $cartItem['cartable_type'],
                'cartable_id' => $cartItem['cartable_id'],
            ]);
        },
    ],
]);
```

### Runtime Configuration

```php
// Update configuration at runtime
$cart->setConfig([
    'tax_rate' => 0.10,
    'currency' => 'GBP'
]);

// Get configuration
$config = $cart->getConfig();
$taxRate = $cart->getCartConfig()->getTaxRate();
```

## PHP Attributes for Tax and Shipping

The package supports PHP 8+ attributes for easy configuration of tax and shipping settings directly on your models.

### Tax Configuration Attribute

```php
use HCart\LaravelMultiCart\Attributes\TaxConfiguration;

#[TaxConfiguration(
    rate: 0.08,
    type: 'percentage',
    included: false,
    compound: false,
    category: 'standard'
)]
class Product extends Model
{
    use Cartable;
    
    // Method-level attributes override class-level
    #[TaxConfiguration(rate: 0.15, type: 'percentage')]
    public function getTaxRate(): float
    {
        return 0.15;
    }
    
    public function getCartPrice(): float
    {
        return $this->price;
    }
}
```

### Shipping Configuration Attribute

```php
use HCart\LaravelMultiCart\Attributes\ShippingConfiguration;

#[ShippingConfiguration(
    cost: 5.99,
    type: 'per_piece',
    piecesPerShipping: 2,
    maxShippingCharges: 3,
    freeShippingThreshold: 100.0
)]
class Product extends Model
{
    use Cartable;
    
    public function getCartPrice(): float
    {
        return $this->price;
    }
}
```

### Attribute Priority System

The package resolves tax and shipping settings in the following priority order:

1. **Explicit attributes** passed to cart operations
2. **Interface implementations** (TaxableInterface, ShippableInterface)
3. **PHP attributes** on model classes, methods, or properties
4. **Default configuration** from config file

```php
// Explicit settings have highest priority
$cart->add($product, 1, [
    'tax_settings' => ['type' => 'fixed', 'value' => 2.0]
]);

// Interface implementation
class Product implements TaxableInterface
{
    public function getTaxRate(): float { return 0.08; }
    // ... other interface methods
}

// PHP attributes (shown above)

// Default config (lowest priority)
'tax' => ['type' => 'percentage', 'value' => 0.05]
```

### Tax and Shipping Interfaces

The package provides interfaces for advanced tax and shipping logic at both the item and cart level:

- `TaxableInterface`: Implement on models to provide per-item tax settings.
- `CartTaxableInterface`: Implement on the cart model for cart-level tax logic.
- `ShippableInterface`: Implement on models to provide per-item shipping settings, including piece-based shipping.
- `CartShippableInterface`: Implement on the cart model for cart-level shipping logic.

#### Example: Implementing Taxable and Shippable on a Product

```php
use HCart\LaravelMultiCart\Contracts\TaxableInterface;
use HCart\LaravelMultiCart\Contracts\ShippableInterface;

class Product extends Model implements TaxableInterface, ShippableInterface
{
    // ...
    public function getTaxSettings(): array { return ['type' => 'percentage', 'value' => 0.2]; }
    public function getTaxRate(): float { return 0.2; }
    public function getTaxType(): string { return 'percentage'; }
    public function isTaxIncluded(): bool { return false; }
    public function isCompoundTax(): bool { return false; }
    public function getTaxCategory(): ?string { return 'standard'; }

    public function getShippingSettings(): array { return ['type' => 'per_piece', 'pieces_per_shipping' => 2, 'max_shipping_charges' => 3, 'value' => 4.0]; }
    public function getShippingCost(): float { return 4.0; }
    public function getShippingType(): string { return 'per_piece'; }
    public function isShippingIncluded(): bool { return false; }
    public function getShippingWeight(): float { return 1.0; }
    public function getShippingDimensions(): array { return ['length' => 1, 'width' => 1, 'height' => 1]; }
    public function getShippingClass(): ?string { return null; }
    public function getShippingZones(): array { return []; }
    public function getPieceBasedShippingConfig(): array { return ['pieces_per_charge' => 2, 'charge_per_group' => 4.0, 'max_charges' => 3]; }
    public function getPiecesPerShipping(): int { return 2; }
    public function getMaxShippingCharges(): ?int { return 3; }
    public function qualifiesForFreeShipping(float $cartTotal): bool { return false; }
}
```

### Piece-Based Shipping Configuration

You can configure piece-based shipping globally or per item. For example, to charge shipping for every 2 pieces, with a maximum of 3 charges:

```php
'shipping' => [
    'type' => 'per_piece',
    'pieces_per_shipping' => 2, // every 2 pieces
    'max_shipping_charges' => 3, // max 3 charges
    'value' => 4.0, // cost per group
    // ...
],
```

Or override per item by implementing the interface as above.

### Bulk Add Items to Cart

You can add multiple items to a cart in a single operation for optimal performance:

```php
$cart = LaravelMultiCart::cart('bulk_cart');
$items = [
    ['cartable' => $product1, 'quantity' => 2, 'attributes' => ['color' => 'red']],
    ['cartable' => $product2, 'quantity' => 3, 'attributes' => ['color' => 'blue']],
    ['cartable' => $product3, 'quantity' => 1, 'price' => 15.99], // Custom price
];
$cart->addBulk($items);
```

The bulk operation supports:
- Custom pricing per item
- Individual tax and shipping settings
- Proper duplicate handling
- Event firing for each item
- Validation and error handling
- Performance optimization for large datasets

### Advanced Bulk Operations

```php
// Bulk add with comprehensive settings
$items = [
    [
        'cartable' => $product1,
        'quantity' => 3,
        'price' => 12.99,                    // Custom price
        'attributes' => [
            'color' => 'red',
            'size' => 'large',
            'tax_settings' => [
                'type' => 'percentage',
                'value' => 8.5,
                'enabled' => true,
            ],
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
        'quantity' => 5,
        'attributes' => [
            'weight' => 2.5,
            'shipping_settings' => [
                'type' => 'weight_based',
                'base_rate' => 5.0,
                'weight_rate' => 1.5,
                'enabled' => true,
            ],
        ],
    ],
];

$cart->addBulk($items);
```

### Bulk Operation Benefits

- **Performance**: Single database transaction for all items
- **Event Batching**: All ItemAdded events fired together
- **Validation**: Pre-validation of all items before processing
- **Duplicate Detection**: Intelligent handling of duplicate items
- **Error Handling**: Comprehensive validation with descriptive errors
- **Memory Efficiency**: Optimized for large datasets (tested with 1000+ items)

### Cart and Item Tax/Shipping Calculation

- If a model implements `TaxableInterface` or `ShippableInterface`, its methods will be used for tax/shipping calculation.
- If not, the cart-level config or model (`CartTaxableInterface`, `CartShippableInterface`) is used.
- Piece-based shipping is calculated as: `ceil(quantity / pieces_per_shipping) * value`, up to `max_shipping_charges`.

## Events

The package dispatches several events that you can listen to:

### Cart Events

```php
// Cart created
HCart\LaravelMultiCart\Events\CartCreated::class

// Cart updated
HCart\LaravelMultiCart\Events\CartUpdated::class

// Cart deleted
HCart\LaravelMultiCart\Events\CartDeleted::class
```

### Item Events

```php
// Item added to cart
HCart\LaravelMultiCart\Events\ItemAdded::class

// Item updated in cart
HCart\LaravelMultiCart\Events\ItemUpdated::class

// Item removed from cart
HCart\LaravelMultiCart\Events\ItemRemoved::class
```

### Event Listeners

Create event listeners to respond to cart events:

```php
<?php

namespace App\Listeners;

use HCart\LaravelMultiCart\Events\ItemAdded;

class LogItemAdded
{
    public function handle(ItemAdded $event)
    {
        Log::info('Item added to cart', [
            'cart_name' => $event->cartName,
            'cartable_type' => $event->cartableType,
            'cartable_id' => $event->cartableId,
            'quantity' => $event->quantity,
            'price' => $event->price,
        ]);
    }
}
```

Register in `EventServiceProvider`:

```php
protected $listen = [
    \HCart\LaravelMultiCart\Events\ItemAdded::class => [
        \App\Listeners\LogItemAdded::class,
    ],
];
```

## Commands

### Cleanup Expired Carts

```bash
php artisan cart:cleanup
```

### Migrate Between Providers

```bash
# Migrate all carts from session to database
php artisan cart:migrate-provider session database

# Force migration without confirmation
php artisan cart:migrate-provider session database --force
```

### Publish Migrations

```bash
php artisan cart:publish-migrations
```

## API Reference

### CartService Methods

```php
// Core operations
add(Model $cartable, int $quantity = 1, array $attributes = []): self
update(string|int $itemId, array $data): self
remove(string|int $itemId): bool
clear(): bool

// Information
count(): int
total(): float
subtotal(): float
tax(): float
items(): Collection
get(string|int $itemId): ?Model
has(Model $cartable): bool
quantity(Model $cartable): int

// Cart management
exists(): bool
delete(): bool
clone(string $newCartName, ?string $provider = null): CartService
convertToProvider(string $newProvider): CartService

// Configuration
setConfig(array $config): self
getConfig(): array
withConfig(CartConfigInterface $config): self

// User association
forUser(Model $user): self
forSession(string $sessionId): self
getUser(): ?Model
getCartId(): ?int

// Meta information
getName(): string
getProvider(): string

addBulk(array $items): self // Add multiple items at once

// Tax and shipping methods
setItemTax(string|int $itemId, array $taxSettings): self
setItemShipping(string|int $itemId, array $shippingSettings): self
totalTax(): float
totalShipping(): float
totalDiscount(): float

// Attribute-based configuration resolution (protected methods)
resolveTaxSettings(Model $cartable, array $attributes): array
resolveShippingSettings(Model $cartable, array $attributes): array
```

### HasCarts Trait Methods

```php
// User cart operations
carts(): HasMany
getCart(string $name, string|\HCart\LaravelMultiCart\Enums\CartProvider $provider = null): CartService
createCart(string $name, array $config = [], string|\HCart\LaravelMultiCart\Enums\CartProvider $provider = null): CartService
deleteCart(string $name): bool
getCartNames(): array
hasCart(string $name): bool
cloneCart(string $from, string $to, ?string $provider = null): CartService
convertCartToProvider(string $cartName, string|HCart\LaravelMultiCart\Enums\CartProvider $provider): CartService
```

### Cartable Trait Methods

```php
// Item cart operations
cartItems(): MorphMany
isInCart(?string $cartName = null): bool
getCartQuantity(?string $cartName = null): int
removeFromCart(?string $cartName = null): bool

// Required implementations
getCartPrice(): float
getCartName(): string
getCartAttributes(): array
```

## Examples

### Comprehensive Example: Modern E-commerce Store

Here's a complete example showcasing all the enhanced features:

```php
use HCart\LaravelMultiCart\Attributes\TaxConfiguration;
use HCart\LaravelMultiCart\Attributes\ShippingConfiguration;
use HCart\LaravelMultiCart\Contracts\TaxableInterface;
use HCart\LaravelMultiCart\Contracts\ShippableInterface;

// Product with PHP attributes
#[TaxConfiguration(rate: 8.5, type: 'percentage')]
#[ShippingConfiguration(cost: 5.99, type: 'per_piece', piecesPerShipping: 2)]
class ElectronicsProduct extends Model
{
    use Cartable;
    
    public function getCartPrice(): float { return $this->price; }
}

// Product implementing interfaces
class ClothingProduct extends Model implements TaxableInterface, ShippableInterface
{
    use Cartable;
    
    public function getTaxSettings(): array
    {
        return ['type' => 'percentage', 'value' => 6.0, 'enabled' => true];
    }
    
    public function getShippingSettings(): array
    {
        return [
            'type' => 'weight_based',
            'base_rate' => 3.0,
            'weight_rate' => 1.0,
            'free_shipping_threshold' => 50.0,
        ];
    }
    
    public function getTaxRate(): float { return 6.0; }
    public function getTaxType(): string { return 'percentage'; }
    public function isTaxIncluded(): bool { return false; }
    public function isCompoundTax(): bool { return false; }
    public function getTaxCategory(): ?string { return 'clothing'; }
    
    public function getShippingCost(): float { return 3.0; }
    public function getShippingType(): string { return 'weight_based'; }
    public function isShippingIncluded(): bool { return false; }
    public function getShippingWeight(): float { return 0.5; }
    public function getShippingDimensions(): array { return []; }
    public function getShippingClass(): ?string { return 'standard'; }
    public function getShippingZones(): array { return ['US']; }
    public function getPiecesPerShipping(): int { return 1; }
    public function getMaxShippingCharges(): ?int { return null; }
    public function qualifiesForFreeShipping(float $cartTotal): bool { return $cartTotal >= 50.0; }
    
    public function getCartPrice(): float { return $this->price; }
}

// Usage example
$electronics = new ElectronicsProduct(['id' => 1, 'price' => 99.99]);
$clothing1 = new ClothingProduct(['id' => 2, 'price' => 29.99]);
$clothing2 = new ClothingProduct(['id' => 3, 'price' => 39.99]);

$cart = LaravelMultiCart::cart('shopping');

// Bulk add with mixed configurations
$items = [
    ['cartable' => $electronics, 'quantity' => 2], // Uses PHP attributes
    ['cartable' => $clothing1, 'quantity' => 1],   // Uses interface
    [
        'cartable' => $clothing2,
        'quantity' => 3,
        'attributes' => [
            'size' => 'large',
            'color' => 'blue',
            'tax_settings' => [              // Explicit override
                'type' => 'fixed',
                'value' => 2.50,
                'enabled' => true,
            ],
        ],
    ],
];

$cart->addBulk($items);

// Calculate totals
$subtotal = $cart->subtotal();     // Item prices × quantities
$tax = $cart->totalTax();         // Calculated using various methods
$shipping = $cart->totalShipping(); // Piece-based + weight-based
$total = $cart->total();          // Subtotal + tax + shipping

echo "Subtotal: $" . number_format($subtotal, 2) . "\n";
echo "Tax: $" . number_format($tax, 2) . "\n";
echo "Shipping: $" . number_format($shipping, 2) . "\n";
echo "Total: $" . number_format($total, 2) . "\n";

/*
Output:
Subtotal: $349.95
Tax: $24.50
Shipping: $18.99
Total: $393.44
*/
```

### E-commerce Shopping Cart

```php
// User adds items to cart
$user = auth()->user();
$cart = $user->getCart('shopping', 'database');

$product = Product::find(1);
$cart->add($product, 2, [
    'size' => 'L',
    'color' => 'blue'
]);

// Apply discount
$cart->setConfig(['discount_rate' => 0.10]);

// Checkout process
$subtotal = $cart->subtotal();
$tax = $cart->tax();
$total = $cart->total();

// Create order
$order = Order::create([
    'user_id' => $user->id,
    'subtotal' => $subtotal,
    'tax' => $tax,
    'total' => $total,
]);

// Add order items
foreach ($cart->items() as $item) {
    $order->items()->create([
        'product_id' => $item['cartable_id'],
        'quantity' => $item['quantity'],
        'price' => $item['price'],
        'attributes' => $item['attributes'],
    ]);
}

// Clear cart after successful order
$cart->clear();
```

### Wishlist Management

```php
$user = auth()->user();
$wishlist = $user->getCart('wishlist', 'database');

$product = Product::find(1);

// Add to wishlist
$wishlist->add($product);

// Check if in wishlist
if ($product->isInCart('wishlist')) {
    // Show "Remove from wishlist" button
}

// Move from wishlist to cart
if ($wishlist->has($product)) {
    $cart = $user->getCart('shopping');
    $cart->add($product);
    
    // Remove from wishlist
    $items = $wishlist->items();
    foreach ($items as $item) {
        if ($item['cartable_id'] == $product->id) {
            $wishlist->remove($item['id']);
            break;
        }
    }
}
```

### Guest to User Cart Migration

```php
// Guest adds items to session cart
$guestCart = LaravelMultiCart::cart('guest_shopping', 'session');
$guestCart->add($product, 1);

// User logs in
$user = auth()->user();

// Get or create user cart
$userCart = $user->getCart('shopping', 'database');

// Merge guest cart into user cart
foreach ($guestCart->items() as $item) {
    $cartableModel = $item['cartable_type'];
    $cartable = $cartableModel::find($item['cartable_id']);
    
    if ($cartable) {
        $userCart->add($cartable, $item['quantity'], $item['attributes']);
    }
}

// Clear guest cart
$guestCart->delete();
```

### Multi-tenant Cart System

```php
// Configure per-tenant carts
$config = new LaravelMultiCartConfig([
    'callbacks' => [
        'unique_item' => function ($cartableId, $cartableType, $attributes) {
            $tenantId = auth()->user()->tenant_id ?? 'default';
            return md5($tenantId . $cartableId . $cartableType . json_encode($attributes));
        },
    ],
]);

$cart = LaravelMultiCart::cart('shopping', 'database')->withConfig($config);
```

## Enhanced Tax and Shipping Features

### Piece-Based Shipping

The package supports sophisticated piece-based shipping where shipping costs are calculated based on quantity groups:

```php
// Configure piece-based shipping
$cart->add($product, 7, [
    'shipping_settings' => [
        'type' => 'per_piece',
        'value' => 4.0,                    // Cost per shipping group
        'pieces_per_shipping' => 2,        // Every 2 pieces = 1 shipping charge
        'max_shipping_charges' => 3,       // Maximum 3 shipping charges
        'enabled' => true,
    ]
]);

// Calculation: ceil(7/2) = 4 groups, but max 3, so 3 × $4.0 = $12.0
$shipping = $cart->totalShipping(); // 12.0
```

### Interface-Based Configuration

Implement interfaces on your models for automatic tax and shipping configuration:

```php
use HCart\LaravelMultiCart\Contracts\TaxableInterface;
use HCart\LaravelMultiCart\Contracts\ShippableInterface;

class Product extends Model implements TaxableInterface, ShippableInterface
{
    use Cartable;
    
    // TaxableInterface implementation
    public function getTaxSettings(): array
    {
        return [
            'type' => 'percentage',
            'value' => 8.5,
            'included' => false,
            'compound' => false,
        ];
    }
    
    public function getTaxRate(): float { return 8.5; }
    public function getTaxType(): string { return 'percentage'; }
    public function isTaxIncluded(): bool { return false; }
    public function isCompoundTax(): bool { return false; }
    public function getTaxCategory(): ?string { return 'standard'; }
    
    // ShippableInterface implementation
    public function getShippingSettings(): array
    {
        return [
            'type' => 'per_piece',
            'value' => 5.0,
            'pieces_per_shipping' => 2,
            'max_shipping_charges' => 3,
        ];
    }
    
    public function getShippingCost(): float { return 5.0; }
    public function getShippingType(): string { return 'per_piece'; }
    public function isShippingIncluded(): bool { return false; }
    public function getShippingWeight(): float { return 1.0; }
    public function getShippingDimensions(): array
    {
        return ['length' => 10, 'width' => 8, 'height' => 5];
    }
    public function getShippingClass(): ?string { return 'standard'; }
    public function getShippingZones(): array { return ['US', 'CA']; }
    public function getPiecesPerShipping(): int { return 2; }
    public function getMaxShippingCharges(): ?int { return 3; }
    public function qualifiesForFreeShipping(float $cartTotal): bool
    {
        return $cartTotal >= 100.0;
    }
    
    public function getCartPrice(): float { return $this->price; }
}
```

### Advanced Shipping Types

The package supports multiple shipping calculation methods:

```php
// Fixed shipping
'shipping_settings' => [
    'type' => 'fixed',
    'value' => 9.99,
]

// Percentage-based shipping
'shipping_settings' => [
    'type' => 'percentage',
    'value' => 5.0, // 5% of item price
]

// Per-piece shipping with limits
'shipping_settings' => [
    'type' => 'per_piece',
    'value' => 3.0,
    'pieces_per_shipping' => 1,     // Every piece gets shipping
    'max_shipping_charges' => 5,    // But max 5 charges
]

// Weight-based shipping
'shipping_settings' => [
    'type' => 'weight_based',
    'base_rate' => 5.0,
    'weight_rate' => 2.0,           // $2 per unit weight
    'free_weight_threshold' => 10.0, // Free under 10 units
]
```

### Free Shipping Thresholds

Configure automatic free shipping based on cart totals:

```php
'shipping_settings' => [
    'type' => 'fixed',
    'value' => 8.99,
    'free_shipping_threshold' => 75.0, // Free shipping over $75
]

// Check if cart qualifies for free shipping
if ($cart->subtotal() >= 75.0) {
    $shipping = 0; // Automatically applied
}
```

## Testing

Run the tests with:

```bash
composer test
```

Run tests with coverage:

```bash
composer test-coverage
```

The package includes comprehensive tests covering:
- **183 tests with 545 assertions**
- All storage providers (session, cache, database, Redis, file)
- Cart operations and bulk operations
- User integration with traits
- Event dispatching and listeners
- Configuration management and callbacks
- Exception handling and recovery
- Performance scenarios and stress testing
- Tax and shipping calculations
- PHP attributes and interface implementations

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [HCart Team](https://github.com/hcart)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
