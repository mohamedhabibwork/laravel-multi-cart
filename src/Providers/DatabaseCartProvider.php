<?php

namespace HCart\LaravelMultiCart\Providers;

use HCart\LaravelMultiCart\Contracts\CartProviderInterface;
use HCart\LaravelMultiCart\Models\Cart;
use Illuminate\Database\DatabaseManager;

class DatabaseCartProvider implements CartProviderInterface
{
    protected DatabaseManager $db;

    protected string $connection;

    protected bool $softDeletes;

    public function __construct(DatabaseManager $db, array $config = [])
    {
        $this->db = $db;
        $this->connection = $config['connection'] ?? 'default';
        $this->softDeletes = $config['soft_deletes'] ?? true;
    }

    public function get(string $cartName): ?array
    {
        $query = $this->getCartQuery()->where('name', $cartName);

        $cart = $query->first();

        if (! $cart) {
            return null;
        }

        // Get stored cart data from config field
        $cartData = $cart->config;

        // If no cart data in config, return basic structure
        if (empty($cartData) || ! isset($cartData['items'])) {
            return [
                'id' => $cart->id,
                'name' => $cart->name,
                'config' => $cartData,
                'user_id' => $cart->user_id,
                'user_type' => $cart->user_type,
                'session_id' => $cart->session_id,
                'expires_at' => $cart->expires_at?->toISOString(),
                'items' => [],
                'created_at' => $cart->created_at?->toISOString(),
                'updated_at' => $cart->updated_at?->toISOString(),
            ];
        }

        return array_merge($cartData, [
            'id' => $cart->id,
            'user_id' => $cart->user_id,
            'user_type' => $cart->user_type,
            'session_id' => $cart->session_id,
            'expires_at' => $cart->expires_at?->toISOString(),
        ]);
    }

    public function put(string $cartName, array $data, ?int $ttl = null): bool
    {
        $cart = $this->getCartQuery()->where('name', $cartName)->first();

        if ($cart) {
            $cart->update([
                'config' => $data, // Store entire cart data in config
                'user_id' => $data['user_id'] ?? null,
                'user_type' => $data['user_type'] ?? null,
                'session_id' => $data['session_id'] ?? null,
                'expires_at' => $ttl ? now()->addSeconds($ttl) : null,
            ]);
        } else {
            $cartModel = app('LaravelMultiCart.config')->getCartModel();
            $cart = $cartModel::create([
                'name' => $cartName,
                'config' => $data, // Store entire cart data in config
                'user_id' => $data['user_id'] ?? null,
                'user_type' => $data['user_type'] ?? null,
                'session_id' => $data['session_id'] ?? null,
                'expires_at' => $ttl ? now()->addSeconds($ttl) : null,
            ]);
        }

        return true;
    }

    public function forget(string $cartName): bool
    {
        $cart = $this->getCartQuery()->where('name', $cartName)->first();

        if (! $cart) {
            return false;
        }

        if ($this->softDeletes) {
            $cart->delete();
        } else {
            $cart->forceDelete();
        }

        return true;
    }

    public function flush(): bool
    {
        $query = $this->getCartQuery();

        if ($this->softDeletes) {
            $query->delete();
        } else {
            $query->forceDelete();
        }

        return true;
    }

    public function exists(string $cartName): bool
    {
        return $this->getCartQuery()->where('name', $cartName)->exists();
    }

    protected function getCartQuery()
    {
        $cartModel = app('LaravelMultiCart.config')->getCartModel();
        $query = $cartModel::on($this->connection);

        if ($this->softDeletes) {
            $query = $query->active();
        }

        return $query;
    }
}
