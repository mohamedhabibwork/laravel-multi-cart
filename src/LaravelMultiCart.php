<?php

namespace HCart\LaravelMultiCart;

use HCart\LaravelMultiCart\Contracts\CartConfigInterface;
use HCart\LaravelMultiCart\Enums\CartProvider;
use HCart\LaravelMultiCart\Services\CartManager;
use HCart\LaravelMultiCart\Services\CartService;
use Illuminate\Foundation\Application;

class LaravelMultiCart
{
    protected CartManager $cartManager;

    protected CartConfigInterface $config;

    public function __construct(protected Application $app)
    {
        $this->cartManager = $app->make(CartManager::class);
        $this->config = $app->make('LaravelMultiCart.config');
    }

    /**
     * Get or create a cart instance
     */
    public function cart(string $name, string|null|CartProvider $provider = null): CartService
    {
        return $this->cartManager->cart($name, $provider);
    }

    /**
     * Create a new cart instance
     */
    public function create(string $name, array $config = [], string|null|CartProvider $provider = null): CartService
    {
        return $this->cartManager->create($name, $config, $provider);
    }

    /**
     * Create a new cart instance in strict mode (throws exception if exists)
     */
    public function createStrict(string $name, array $config = [], string|null|CartProvider $provider = null): CartService
    {
        return $this->cartManager->createStrict($name, $config, $provider);
    }

    /**
     * Delete a cart instance
     */
    public function delete(string $name, string|null|CartProvider $provider = null): bool
    {
        return $this->cartManager->delete($name, $provider);
    }

    /**
     * Check if cart exists
     */
    public function exists(string $name, string|null|CartProvider $provider = null): bool
    {
        return $this->cartManager->exists($name, $provider);
    }

    /**
     * Get all cart names
     */
    public function getAllCartNames(string|null|CartProvider $provider = null): array
    {
        return $this->cartManager->getAllCartNames($provider);
    }

    /**
     * Set global configuration
     */
    public function setConfig(CartConfigInterface $config): void
    {
        $this->config = $config;
        $this->app->instance('LaravelMultiCart.config', $config);
    }

    /**
     * Get global configuration
     */
    public function getConfig(): CartConfigInterface
    {
        return $this->config;
    }

    /**
     * Flush all carts from a provider
     */
    public function flush(string|null|CartProvider $provider = null): bool
    {
        return $this->cartManager->flush($provider);
    }

    /**
     * Get cart manager instance
     */
    public function getManager(): CartManager
    {
        return $this->cartManager;
    }
}
