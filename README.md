# Laravel Multi-Cart Package

[![Latest Version on Packagist](https://img.shields.io/packagist/v/hcart/laravel-multi-cart.svg?style=flat-square)](https://packagist.org/packages/hcart/laravel-multi-cart)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/hcart/laravel-multi-cart/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/hcart/laravel-multi-cart/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/hcart/laravel-multi-cart/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/hcart/laravel-multi-cart/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
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
- [Storage Providers](#storage-providers)
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
- **Configurable Storage Providers**: Choose from session, cache, database, Redis, or file storage providers
- **Polymorphic Relationships**: Add any Eloquent model to carts with full relationship support
- **JSON Configuration Storage**: Flexible configuration with JSON/JSONB support for enhanced flexibility
- **Soft Delete Support**: Built-in soft delete functionality with `deleted_at` timestamps for data recovery
- **Type-Safe Implementation**: Modern PHP 8.2+ features with strict typing and comprehensive interfaces
- **Comprehensive Event System**: Listen to cart creation, updates, deletions, and item changes
- **Built-in Validation**: Automatic validation and error handling with custom exceptions
- **Trait Support**: Easy integration with User models and cartable items using Laravel traits
- **Automatic Cleanup**: Scheduled cleanup of expired carts with configurable retention policies
- **Custom Callbacks**: Extensible callback system for item uniqueness, updates, and removals
- **Provider Migration**: Seamless migration between different storage providers
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
use HCart\LaravelMultiCart\Facades\LaravelMultiCart;

// Get or create a cart
$cart = LaravelMultiCart::cart('shopping');

// Create cart with specific provider
$cart = LaravelMultiCart::cart('wishlist', 'database');

// Create cart with custom configuration
$cart = LaravelMultiCart::create('premium', [
    'tax_rate' => 0.15,
    'currency' => 'EUR'
], 'database');

// Check if cart exists
if (LaravelMultiCart::exists('shopping')) {
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

// Get user's cart
$cart = $user->getCart('shopping');

// Get cart with specific provider
$cart = $user->getCart('wishlist', 'database');

// Check if user has cart
if ($user->hasCart('favorites')) {
    $favorites = $user->getCart('favorites');
}

// Get all user cart names
$cartNames = $user->getCartNames();

// Clone user's cart
$clonedCart = $user->cloneCart('shopping', 'shopping_backup');

// Convert cart to different provider
$convertedCart = $user->convertCartToProvider('shopping', 'database');

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

## Storage Providers

### Session Provider

Best for guest users and temporary carts:

```php
$cart = LaravelMultiCart::cart('guest_cart', 'session');
```

### Database Provider

Best for persistent user carts with relationships:

```php
$cart = LaravelMultiCart::cart('user_cart', 'database');

// Supports user associations
$cart->forUser($user);

// Get cart ID for relationships
$cartId = $cart->getCartId();
```

### Cache Provider

Best for high-performance scenarios:

```php
$cart = LaravelMultiCart::cart('fast_cart', 'cache');
```

### Redis Provider

Best for distributed applications:

```php
$cart = LaravelMultiCart::cart('distributed_cart', 'redis');
```

### File Provider

Best for simple applications without database/cache:

```php
$cart = LaravelMultiCart::cart('file_cart', 'file');
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
```

### HasCarts Trait Methods

```php
// User cart operations
carts(): HasMany
getCart(string $name, ?string $provider = null): CartService
createCart(string $name, array $config = [], ?string $provider = null): CartService
deleteCart(string $name): bool
getCartNames(): array
hasCart(string $name): bool
cloneCart(string $from, string $to, ?string $provider = null): CartService
convertCartToProvider(string $cartName, string $provider): CartService
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
- All storage providers
- Cart operations
- User integration
- Event dispatching
- Configuration management
- Exception handling
- Performance scenarios

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
