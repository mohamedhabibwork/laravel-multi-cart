<?php

namespace HCart\LaravelMultiCart\Services;

use HCart\LaravelMultiCart\Contracts\CartProviderInterface;
use HCart\LaravelMultiCart\Exceptions\InvalidCartProviderException;
use Illuminate\Foundation\Application;

class CartManager
{
    protected Application $app;

    protected array $providers = [];

    protected array $carts = [];

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Get or create a cart instance
     */
    public function cart(string $name, ?string $provider = null): CartService
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
    public function create(string $name, array $config = [], ?string $provider = null): CartService
    {
        $cartService = $this->cart($name, $provider);
        $cartService->setConfig($config);

        return $cartService;
    }

    /**
     * Create a new cart instance in strict mode (throws exception if exists)
     */
    public function createStrict(string $name, array $config = [], ?string $provider = null): CartService
    {
        if ($this->exists($name, $provider)) {
            throw new \HCart\LaravelMultiCart\Exceptions\CartExistsException($name, $provider);
        }

        return $this->create($name, $config, $provider);
    }

    /**
     * Delete a cart instance
     */
    public function delete(string $name, ?string $provider = null): bool
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
    public function exists(string $name, ?string $provider = null): bool
    {
        $provider = $provider ?: $this->getDefaultProvider();
        $cartProvider = $this->getProvider($provider);

        return $cartProvider->exists($name);
    }

    /**
     * Get all cart names
     */
    public function getAllCartNames(?string $provider = null): array
    {
        // This would need provider-specific implementation
        // For now, return empty array as this is complex for some providers
        return [];
    }

    /**
     * Flush all carts from a provider
     */
    public function flush(?string $provider = null): bool
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
    public function getProvider(string $name): CartProviderInterface
    {
        if (! isset($this->providers[$name])) {
            $this->providers[$name] = $this->createProvider($name);
        }

        return $this->providers[$name];
    }

    /**
     * Create a cart provider instance
     */
    protected function createProvider(string $name): CartProviderInterface
    {
        $config = config("laravel-multi-cart.providers.{$name}");

        if (! $config) {
            throw new InvalidCartProviderException($name);
        }

        $driver = $config['driver'];

        return match ($driver) {
            'session' => $this->app->make('cart.provider.session', ['config' => $config]),
            'cache' => $this->app->make('cart.provider.cache', ['config' => $config]),
            'database' => $this->app->make('cart.provider.database', ['config' => $config]),
            'redis' => $this->app->make('cart.provider.redis', ['config' => $config]),
            'file' => $this->app->make('cart.provider.file', ['config' => $config]),
            default => throw new InvalidCartProviderException($driver)
        };
    }

    /**
     * Get the default provider name
     */
    protected function getDefaultProvider(): string
    {
        return config('laravel-multi-cart.default', 'session');
    }

    /**
     * Generate a unique key for cart instance
     */
    protected function getCartKey(string $name, string $provider): string
    {
        return $name.'_'.$provider;
    }
}
