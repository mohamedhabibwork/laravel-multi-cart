<?php

namespace HCart\LaravelMultiCart\Traits;

use HCart\LaravelMultiCart\Facades\LaravelMultiCart;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasCarts
{
    public function carts(): MorphMany
    {
        $cartModel = app('LaravelMultiCart.config')->getCartModel();

        return $this->morphMany($cartModel, 'user');
    }

    public function getCart(string $name, ?string $provider = null): \HCart\LaravelMultiCart\Services\CartService
    {
        // If no provider specified, try to find the cart in database first (most reliable for user carts)
        if ($provider === null) {
            if ($this->carts()->where('name', $name)->exists()) {
                $provider = 'database';
            } else {
                // Fall back to default provider
                $provider = config('laravel-multi-cart.default', 'session');
            }
        }

        return LaravelMultiCart::cart($name, $provider)->forUser($this);
    }

    public function createCart(string $name, array $config = [], ?string $provider = null): \HCart\LaravelMultiCart\Services\CartService
    {
        // Default to database provider for user carts if no provider specified
        $provider = $provider ?? 'database';

        $cart = LaravelMultiCart::create($name, $config, $provider)->forUser($this);

        // Force loading and saving to ensure the cart is persisted with user association
        $cart->count(); // This triggers loading

        return $cart;
    }

    public function deleteCart(string $name): bool
    {
        // For user carts, check database provider first (most reliable)
        if ($this->carts()->where('name', $name)->exists()) {
            return LaravelMultiCart::cart($name, 'database')->delete();
        }

        // Fall back to default provider
        return LaravelMultiCart::cart($name)->delete();
    }

    public function getCartNames(): array
    {
        // Always check database for user carts (most reliable)
        return $this->carts()->pluck('name')->toArray();
    }

    public function hasCart(string $name): bool
    {
        // First check database provider (most reliable for user association)
        if ($this->carts()->where('name', $name)->exists()) {
            return true;
        }

        // For other providers, get the cart and check if it exists and is associated with this user
        $cart = $this->getCart($name);

        return $cart->exists() && $cart->getUser() && $cart->getUser()->getKey() === $this->getKey();
    }

    /**
     * Clone user's cart to a new cart name
     */
    public function cloneCart(string $originalName, string $newName, ?string $provider = null): \HCart\LaravelMultiCart\Services\CartService
    {
        $originalCart = $this->getCart($originalName, $provider);

        return $originalCart->clone($newName, $provider);
    }

    /**
     * Convert user's cart to a different provider
     */
    public function convertCartToProvider(string $cartName, string $newProvider): \HCart\LaravelMultiCart\Services\CartService
    {
        $cart = $this->getCart($cartName);

        return $cart->convertToProvider($newProvider);
    }
}
