<?php

namespace HCart\LaravelMultiCart\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;

trait Cartable
{
    public function cartItems(): MorphMany
    {
        $cartItemModel = app('LaravelMultiCart.config')->getCartItemModel();

        return $this->morphMany($cartItemModel, 'cartable');
    }

    public function isInCart(?string $cartName = null): bool
    {
        // Check in carts using the cart system's search functionality
        if ($cartName) {
            // Check specific cart - try database provider first
            $cart = app(\HCart\LaravelMultiCart\Services\CartManager::class)->cart($cartName, 'database');
            if ($cart->exists() && $cart->has($this)) {
                return true;
            }

            // Also check session carts
            $sessionCart = app(\HCart\LaravelMultiCart\Services\CartManager::class)->cart($cartName, 'session');
            if ($sessionCart->exists() && $sessionCart->has($this)) {
                return true;
            }

            return false;
        }

        // Check all database carts first
        $cartModel = app('LaravelMultiCart.config')->getCartModel();
        $carts = $cartModel::all();

        foreach ($carts as $cartRecord) {
            $cart = app(\HCart\LaravelMultiCart\Services\CartManager::class)->cart($cartRecord->name, 'database');
            if ($cart->has($this)) {
                return true;
            }
        }

        return false;
    }

    public function getCartQuantity(?string $cartName = null): int
    {
        $totalQuantity = 0;

        if ($cartName) {
            // Check specific cart - try database provider first
            $cart = app(\HCart\LaravelMultiCart\Services\CartManager::class)->cart($cartName, 'database');
            if ($cart->exists()) {
                $totalQuantity += $cart->quantity($this);
            }

            // Also check session carts
            $sessionCart = app(\HCart\LaravelMultiCart\Services\CartManager::class)->cart($cartName, 'session');
            if ($sessionCart->exists()) {
                $totalQuantity += $sessionCart->quantity($this);
            }

            return $totalQuantity;
        }

        // Check all database carts
        $cartModel = app('LaravelMultiCart.config')->getCartModel();
        $carts = $cartModel::all();

        foreach ($carts as $cartRecord) {
            $cart = app(\HCart\LaravelMultiCart\Services\CartManager::class)->cart($cartRecord->name, 'database');
            $totalQuantity += $cart->quantity($this);
        }

        return $totalQuantity;
    }

    public function removeFromCart(?string $cartName = null): bool
    {
        $removedAny = false;

        if ($cartName) {
            // Remove from specific cart - try database provider first
            $cart = app(\HCart\LaravelMultiCart\Services\CartManager::class)->cart($cartName, 'database');
            if ($cart->exists() && $cart->has($this)) {
                // Find the item ID and remove it
                $items = $cart->items();
                foreach ($items as $item) {
                    if ($item['cartable_id'] == $this->getKey() && $item['cartable_type'] == get_class($this)) {
                        $cart->remove($item['id']);
                        $removedAny = true;
                        break; // Remove only the first matching item
                    }
                }
            }

            // Also check session carts
            $sessionCart = app(\HCart\LaravelMultiCart\Services\CartManager::class)->cart($cartName, 'session');
            if ($sessionCart->exists() && $sessionCart->has($this)) {
                $items = $sessionCart->items();
                foreach ($items as $item) {
                    if ($item['cartable_id'] == $this->getKey() && $item['cartable_type'] == get_class($this)) {
                        $sessionCart->remove($item['id']);
                        $removedAny = true;
                        break; // Remove only the first matching item
                    }
                }
            }

            return $removedAny;
        }

        // Remove from all database carts
        $cartModel = app('LaravelMultiCart.config')->getCartModel();
        $carts = $cartModel::all();

        foreach ($carts as $cartRecord) {
            $cart = app(\HCart\LaravelMultiCart\Services\CartManager::class)->cart($cartRecord->name, 'database');
            if ($cart->has($this)) {
                $items = $cart->items();
                foreach ($items as $item) {
                    if ($item['cartable_id'] == $this->getKey() && $item['cartable_type'] == get_class($this)) {
                        $cart->remove($item['id']);
                        $removedAny = true;
                        break; // Remove only the first matching item
                    }
                }
            }
        }

        return $removedAny;
    }

    public function getCartPrice(): float
    {
        return (float) $this->price ?? 0.0;
    }

    public function getCartName(): string
    {
        return $this->name ?? $this->title ?? (string) $this->id;
    }

    public function getCartAttributes(): array
    {
        return [];
    }
}
