<?php

namespace HCart\LaravelMultiCart\Providers;

use HCart\LaravelMultiCart\Contracts\CartProviderInterface;
use Illuminate\Redis\RedisManager;

class RedisCartProvider implements CartProviderInterface
{
    protected RedisManager $redis;

    protected string $prefix;

    protected int $ttl;

    protected string $connection;

    public function __construct(RedisManager $redis, array $config = [])
    {
        $this->redis = $redis;
        $this->prefix = $config['prefix'] ?? 'laravel_multi_cart_';
        $this->ttl = $config['ttl'] ?? 3600;
        $this->connection = $config['connection'] ?? 'default';
    }

    public function get(string $cartName): ?array
    {
        $data = $this->redis->connection($this->connection)->get($this->getKey($cartName));

        return $data ? json_decode($data, true) : null;
    }

    public function put(string $cartName, array $data, ?int $ttl = null): bool
    {
        $ttl = $ttl ?? $this->ttl;
        $key = $this->getKey($cartName);
        $value = json_encode($data);

        if ($ttl > 0) {
            return $this->redis->connection($this->connection)->setex($key, $ttl, $value);
        }

        return $this->redis->connection($this->connection)->set($key, $value);
    }

    public function forget(string $cartName): bool
    {
        return $this->redis->connection($this->connection)->del($this->getKey($cartName)) > 0;
    }

    public function flush(): bool
    {
        $keys = $this->redis->connection($this->connection)->keys($this->prefix.'*');

        if (empty($keys)) {
            return true;
        }

        return $this->redis->connection($this->connection)->del($keys) > 0;
    }

    public function exists(string $cartName): bool
    {
        return $this->redis->connection($this->connection)->exists($this->getKey($cartName)) > 0;
    }

    public function getAllNames(): array
    {
        $keys = $this->redis->connection($this->connection)->keys($this->prefix.'*');
        $cartNames = [];

        foreach ($keys as $key) {
            if (str_starts_with($key, $this->prefix)) {
                $cartNames[] = substr($key, strlen($this->prefix));
            }
        }

        return $cartNames;
    }

    protected function getKey(string $cartName): string
    {
        return $this->prefix.$cartName;
    }
}
