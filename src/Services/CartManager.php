<?php

namespace HCart\LaravelMultiCart\Services;

use HCart\LaravelMultiCart\Contracts\CartProviderInterface;
use HCart\LaravelMultiCart\Enums\CartProvider;
use HCart\LaravelMultiCart\Exceptions\InvalidCartProviderException;
use Illuminate\Foundation\Application;

class CartManager
{
    protected array $providers = [];

    protected array $carts = [];

    public function __construct(protected Application $app) {}

    /**
     * Get or create a cart instance
     */
    public function cart(string $name, string|null|CartProvider $provider = null): CartService
    {
        $provider = $provider ?: $this->getDefaultProvider();

        // Validate provider exists before creating cart service
        $this->getProvider($provider);

        $key = $this->getCartKey($name, $provider);

        if (! isset($this->carts[$key])) {
            $this->carts[$key] = new CartService(
                $this,
                $this->app->make('LaravelMultiCart.config'),
                $name,
                $provider
            );
        }

        return $this->carts[$key];
    }

    /**
     * Create a new cart instance
     */
    public function create(string $name, array $config = [], string|null|CartProvider $provider = null): CartService
    {
        $cartService = $this->cart($name, $provider);
        $cartService->setConfig($config);

        return $cartService;
    }

    /**
     * Create a new cart instance in strict mode (throws exception if exists)
     */
    public function createStrict(string $name, array $config = [], string|null|CartProvider $provider = null): CartService
    {
        if ($this->exists($name, $provider)) {
            throw new \HCart\LaravelMultiCart\Exceptions\CartExistsException($name, $provider);
        }

        return $this->create($name, $config, $provider);
    }

    /**
     * Delete a cart instance
     */
    public function delete(string $name, string|null|CartProvider $provider = null): bool
    {
        $provider = $provider ?: $this->getDefaultProvider();
        $cartProvider = $this->getProvider($provider);

        // Remove from cache
        $key = $this->getCartKey($name, $provider);
        unset($this->carts[$key]);

        return $cartProvider->forget($name);
    }

    /**
     * Check if cart exists
     */
    public function exists(string $name, string|null|CartProvider $provider = null): bool
    {
        $provider = $provider ?: $this->getDefaultProvider();
        $cartProvider = $this->getProvider($provider);

        return $cartProvider->exists($name);
    }

    /**
     * Get all cart names
     */
    public function getAllCartNames(string|null|CartProvider $provider = null): array
    {
        $provider = $provider ?: $this->getDefaultProvider();
        $cartProvider = $this->getProvider($provider);

        return $cartProvider->getAllNames();
    }

    /**
     * Flush all carts from a provider
     */
    public function flush(string|null|CartProvider $provider = null): bool
    {
        $provider = $provider ?: $this->getDefaultProvider();
        $cartProvider = $this->getProvider($provider);

        // Clear cache
        $this->carts = array_filter($this->carts, function ($key) use ($provider) {
            return ! str_ends_with($key, '_'.$provider);
        }, ARRAY_FILTER_USE_KEY);

        return $cartProvider->flush();
    }

    /**
     * Get a cart provider instance
     */
    public function getProvider(string|CartProvider $name): CartProviderInterface
    {
        $name = $name instanceof CartProvider ? $name->value : $name;

        if (! isset($this->providers[$name])) {
            $this->providers[$name] = $this->createProvider($name);
        }

        return $this->providers[$name];
    }

    /**
     * Create a cart provider instance
     */
    protected function createProvider(string|CartProvider $name): CartProviderInterface
    {
        $name = $name instanceof CartProvider ? $name->value : $name;
        $config = config("laravel-multi-cart.providers.{$name}");

        if (! $config) {
            throw new InvalidCartProviderException($name);
        }

        $driver = $config['driver'] ?? $name;

        // Validate driver using enum
        if (! CartProvider::isValid($driver)) {
            throw new InvalidCartProviderException($driver);
        }

        $providerEnum = CartProvider::fromString($driver);

        return $this->app->make("cart.provider.{$providerEnum->value}", ['config' => $config]);
    }

    /**
     * Get the default provider name
     */
    protected function getDefaultProvider(): string|CartProvider
    {
        return config('laravel-multi-cart.default', CartProvider::SESSION->value);
    }

    /**
     * Get all available provider names
     */
    public function getAvailableProviders(): array
    {
        return CartProvider::getAll();
    }

    /**
     * Get provider information
     */
    public function getProviderInfo(string|CartProvider $provider): array
    {
        $providerEnum = $provider instanceof CartProvider ? $provider : CartProvider::fromString($provider);

        return [
            'name' => $providerEnum->value,
            'display_name' => $providerEnum->getDisplayName(),
            'description' => $providerEnum->getDescription(),
            'is_stateless' => $providerEnum->isStateless(),
            'is_stateful' => $providerEnum->isStateful(),
            'supports_merging' => $providerEnum->supportsMerging(),
        ];
    }

    /**
     * Get information for all available providers
     */
    public function getAllProviderInfo(): array
    {
        return array_map(
            fn ($provider) => $this->getProviderInfo($provider),
            $this->getAvailableProviders()
        );
    }

    /**
     * Generate a unique key for cart instance
     */
    protected function getCartKey(string $name, string|CartProvider $provider): string
    {
        $provider = $provider instanceof CartProvider ? $provider->value : $provider;

        return $name.'_'.$provider;
    }
}
