<?php

namespace HCart\LaravelMultiCart\Providers;

use HCart\LaravelMultiCart\Contracts\CartProviderInterface;
use Illuminate\Session\SessionManager;

class SessionCartProvider implements CartProviderInterface
{
    protected SessionManager $session;

    protected string $prefix;

    public function __construct(SessionManager $session, array $config = [])
    {
        $this->session = $session;
        $this->prefix = $config['prefix'] ?? 'laravel_multi_cart_';
    }

    public function get(string $cartName): ?array
    {
        return $this->session->get($this->getKey($cartName));
    }

    public function put(string $cartName, array $data, ?int $ttl = null): bool
    {
        $this->session->put($this->getKey($cartName), $data);

        return true;
    }

    public function forget(string $cartName): bool
    {
        $this->session->forget($this->getKey($cartName));

        return true;
    }

    public function flush(): bool
    {
        $keys = $this->session->all();

        foreach ($keys as $key => $value) {
            if (str_starts_with($key, $this->prefix)) {
                $this->session->forget($key);
            }
        }

        return true;
    }

    public function exists(string $cartName): bool
    {
        return $this->session->has($this->getKey($cartName));
    }

    protected function getKey(string $cartName): string
    {
        return $this->prefix.$cartName;
    }
}
