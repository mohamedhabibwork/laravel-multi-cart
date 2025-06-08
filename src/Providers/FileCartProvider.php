<?php

namespace HCart\LaravelMultiCart\Providers;

use HCart\LaravelMultiCart\Contracts\CartProviderInterface;
use Illuminate\Filesystem\Filesystem;

class FileCartProvider implements CartProviderInterface
{
    protected Filesystem $files;

    protected string $path;

    protected int $ttl;

    public function __construct(Filesystem $files, array $config = [])
    {
        $this->files = $files;
        $this->path = $config['path'] ?? storage_path('laravel_multi_cart');
        $this->ttl = $config['ttl'] ?? 3600;

        if (! $this->files->exists($this->path)) {
            $this->files->makeDirectory($this->path, 0755, true);
        }
    }

    public function get(string $cartName): ?array
    {
        $filePath = $this->getFilePath($cartName);

        if (! $this->files->exists($filePath)) {
            return null;
        }

        $content = $this->files->get($filePath);
        $data = json_decode($content, true);

        // Check if file has expired
        if (isset($data['expires_at']) && $data['expires_at'] < time()) {
            $this->forget($cartName);

            return null;
        }

        return $data['data'] ?? null;
    }

    public function put(string $cartName, array $data, ?int $ttl = null): bool
    {
        $ttl = $ttl ?? $this->ttl;
        $expiresAt = $ttl > 0 ? time() + $ttl : null;

        $fileData = [
            'data' => $data,
            'expires_at' => $expiresAt,
            'created_at' => time(),
        ];

        return $this->files->put($this->getFilePath($cartName), json_encode($fileData)) !== false;
    }

    public function forget(string $cartName): bool
    {
        $filePath = $this->getFilePath($cartName);

        if ($this->files->exists($filePath)) {
            return $this->files->delete($filePath);
        }

        return true;
    }

    public function flush(): bool
    {
        $files = $this->files->glob($this->path.'/*.json');

        foreach ($files as $file) {
            $this->files->delete($file);
        }

        return true;
    }

    public function exists(string $cartName): bool
    {
        $filePath = $this->getFilePath($cartName);

        if (! $this->files->exists($filePath)) {
            return false;
        }

        // Check if file has expired
        $content = $this->files->get($filePath);
        $data = json_decode($content, true);

        if (isset($data['expires_at']) && $data['expires_at'] < time()) {
            $this->forget($cartName);

            return false;
        }

        return true;
    }

    protected function getFilePath(string $cartName): string
    {
        return $this->path.'/'.md5($cartName).'.json';
    }
}
