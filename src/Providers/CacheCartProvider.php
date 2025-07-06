<?php

namespace HCart\LaravelMultiCart\Providers;

use HCart\LaravelMultiCart\Contracts\CartProviderInterface;
use Illuminate\Cache\CacheManager;

class CacheCartProvider implements CartProviderInterface
{
    protected CacheManager $cache;

    protected string $prefix;

    protected int $ttl;

    protected string $store;

    public function __construct(CacheManager $cache, array $config = [])
    {
        $this->cache = $cache;
        $this->prefix = $config['prefix'] ?? 'laravel_multi_cart_';
        $this->ttl = $config['ttl'] ?? 3600;
        $this->store = $config['store'] ?? 'default';
    }

    public function get(string $cartName): ?array
    {
        return $this->cache->store($this->store)->get($this->getKey($cartName));
    }

    public function put(string $cartName, array $data, ?int $ttl = null): bool
    {
        $ttl = $ttl ?? $this->ttl;

        if ($ttl > 0) {
            return $this->cache->store($this->store)->put($this->getKey($cartName), $data, $ttl);
        }

        return $this->cache->store($this->store)->forever($this->getKey($cartName), $data);
    }

    public function forget(string $cartName): bool
    {
        return $this->cache->store($this->store)->forget($this->getKey($cartName));
    }

    public function flush(): bool
    {
        // Note: This is a simplified implementation.
        // In a real-world scenario, you might want to use cache tags if supported.
        return $this->cache->store($this->store)->flush();
    }

    public function exists(string $cartName): bool
    {
        return $this->cache->store($this->store)->has($this->getKey($cartName));
    }

    public function getAllNames(): array
    {
        // Note: This implementation is limited as most cache stores don't support key listing
        // This is a basic implementation that may not work with all cache drivers
        return [];
    }

    protected function getKey(string $cartName): string
    {
        return $this->prefix.$cartName;
    }
}
