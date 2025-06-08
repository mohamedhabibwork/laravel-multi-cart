<?php

namespace HCart\LaravelMultiCart\Config;

use HCart\LaravelMultiCart\Contracts\CartConfigInterface;
use HCart\LaravelMultiCart\Models\Cart;
use HCart\LaravelMultiCart\Models\CartItem;

class LaravelMultiCartConfig implements CartConfigInterface
{
    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->getDefaultConfig(), $config);
    }

    public function getCartModel(): string
    {
        return $this->config['models']['cart'] ?? Cart::class;
    }

    public function getCartItemModel(): string
    {
        return $this->config['models']['cart_item'] ?? CartItem::class;
    }

    public function getUniqueItemCallback(): ?callable
    {
        return $this->config['callbacks']['unique_item'] ?? $this->getDefaultUniqueCallback();
    }

    public function getTaxRate(): float
    {
        return (float) ($this->config['tax_rate'] ?? 0.0);
    }

    public function getCurrency(): string
    {
        return $this->config['currency'] ?? 'USD';
    }

    public function getDefaultProvider(): string
    {
        return $this->config['default_provider'] ?? 'session';
    }

    public function shouldPreventDuplicates(): bool
    {
        return $this->config['prevent_duplicates'] ?? true;
    }

    public function getItemUpdateCallback(): ?callable
    {
        return $this->config['callbacks']['item_update'] ?? null;
    }

    public function getItemRemoveCallback(): ?callable
    {
        return $this->config['callbacks']['item_remove'] ?? null;
    }

    protected function getDefaultConfig(): array
    {
        return [
            'models' => [
                'cart' => Cart::class,
                'cart_item' => CartItem::class,
            ],
            'callbacks' => [
                'unique_item' => null,
                'item_update' => null,
                'item_remove' => null,
            ],
            'tax_rate' => 0.0,
            'currency' => 'USD',
            'default_provider' => 'session',
            'prevent_duplicates' => true,
        ];
    }

    protected function getDefaultUniqueCallback(): callable
    {
        return function ($cartableId, $cartableType, $attributes = []) {
            // Default: items are unique by model ID and type only
            return md5($cartableId.$cartableType);
        };
    }

    public function setConfig(array $config): self
    {
        $this->config = array_merge($this->config, $config);

        return $this;
    }

    public function getConfig(?string $key = null)
    {
        if ($key === null) {
            return $this->config;
        }

        return data_get($this->config, $key);
    }
}
