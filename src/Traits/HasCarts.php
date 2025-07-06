<?php

namespace HCart\LaravelMultiCart\Traits;

use HCart\LaravelMultiCart\Enums\CartProvider;
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
                $provider = CartProvider::DATABASE;
            } else {
                // Fall back to default provider
                $provider = config('laravel-multi-cart.default', CartProvider::SESSION->value);
            }
        }

        return LaravelMultiCart::cart($name, $provider)->forUser($this);
    }

    public function createCart(string $name, array $config = [], ?string $provider = null): \HCart\LaravelMultiCart\Services\CartService
    {
        // Default to database provider for user carts if no provider specified
        $provider = $provider ?? CartProvider::DATABASE->value;

        $cart = LaravelMultiCart::create($name, $config, $provider)->forUser($this);

        // Force loading and saving to ensure the cart is persisted with user association
        $cart->count(); // This triggers loading

        return $cart;
    }

    public function deleteCart(string $name): bool
    {
        // For user carts, check database provider first (most reliable)
        if ($this->carts()->where('name', $name)->exists()) {
            return LaravelMultiCart::cart($name, CartProvider::DATABASE->value)->delete();
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
    public function cloneCart(string $originalName, string $newName, string|null|CartProvider $provider = null): \HCart\LaravelMultiCart\Services\CartService
    {
        $originalCart = $this->getCart($originalName, $provider);

        return $originalCart->clone($newName, $provider);
    }

    /**
     * Convert user's cart to a different provider
     */
    public function convertCartToProvider(string $cartName, string|CartProvider $newProvider, array $options = []): \HCart\LaravelMultiCart\Services\CartService
    {
        $cart = $this->getCart($cartName);

        return $cart->convertToProvider($newProvider, $options);
    }

    /**
     * Convert user's cart to database provider with merge options
     */
    public function convertCartToDatabase(string $cartName, array $options = []): \HCart\LaravelMultiCart\Services\CartService
    {
        return $this->convertCartToProvider($cartName, CartProvider::DATABASE, $options);
    }

    /**
     * Merge two user carts
     */
    public function mergeCarts(string $sourceCartName, string $targetCartName, string $mergeStrategy = 'merge'): \HCart\LaravelMultiCart\Services\CartService
    {
        $sourceCart = $this->getCart($sourceCartName);

        return $sourceCart->convertToProvider(CartProvider::DATABASE, [
            'merge_with_existing' => true,
            'target_cart_name' => $targetCartName,
            'merge_strategy' => $mergeStrategy,
        ]);
    }

    /**
     * Get available carts for merging for this user
     */
    public function getAvailableCartsForMerging(?string $excludeCartName = null): array
    {
        $carts = $this->carts()->get(['name', 'id', 'created_at', 'updated_at']);

        if ($excludeCartName) {
            $carts = $carts->where('name', '!=', $excludeCartName);
        }

        return $carts->toArray();
    }

    /**
     * Get cart summaries for all user carts
     */
    public function getCartSummaries(): array
    {
        $cartNames = $this->getCartNames();
        $summaries = [];

        foreach ($cartNames as $cartName) {
            $cart = $this->getCart($cartName);
            $summaries[] = $cart->getSummary();
        }

        return $summaries;
    }
}
