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

    public function getDiscountSettings(): array
    {
        return $this->config['discount'] ?? $this->getDefaultDiscountSettings();
    }

    public function getTaxSettings(): array
    {
        $settings = $this->config['tax'] ?? $this->getDefaultTaxSettings();

        // Ensure the value is always calculated based on current tax rate
        // If no explicit tax settings value is provided, calculate from tax_rate
        if (! isset($this->config['tax']['value']) || ($settings['value'] == 0 && $this->getTaxRate() > 0)) {
            $settings['value'] = $this->getTaxRate() * 100;
        }

        return $settings;
    }

    public function getShippingSettings(): array
    {
        return $this->config['shipping'] ?? $this->getDefaultShippingSettings();
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
            'discount' => $this->getDefaultDiscountSettings(),
            'tax' => $this->getDefaultTaxSettings(),
            'shipping' => $this->getDefaultShippingSettings(),
        ];
    }

    protected function getDefaultDiscountSettings(): array
    {
        return [
            'enabled' => true,
            'type' => 'percentage', // 'percentage', 'fixed', or 'tiered'
            'value' => 0.0,
            'included' => false, // true if discount is already included in price
            'per_item' => false, // true if discount can be applied per item
            'minimum_amount' => null, // minimum cart amount for discount to apply
            'maximum_amount' => null, // maximum discount amount
            'tiers' => [], // tiered discount configuration
        ];
    }

    protected function getDefaultTaxSettings(): array
    {
        return [
            'enabled' => true,
            'type' => 'percentage', // 'percentage' or 'fixed'
            'value' => $this->getTaxRate() * 100, // Convert from decimal (0.10) to percentage (10)
            'included' => false, // true if tax is already included in price
            'per_item' => false, // true if tax can be applied per item
            'compound' => false, // true if tax is calculated after discount
        ];
    }

    protected function getDefaultShippingSettings(): array
    {
        return [
            'enabled' => true,
            'type' => 'fixed', // 'percentage', 'fixed', 'per_piece', or 'weight_based'
            'value' => 0.0,
            'included' => false, // true if shipping is already included in price
            'per_item' => false, // true if shipping can be applied per item
            'free_shipping_threshold' => null, // minimum cart amount for free shipping

            // Per-piece shipping settings
            'pieces_per_shipping' => 2, // every X pieces gets shipping cost
            'max_shipping_charges' => null, // null means unlimited charges

            // Weight-based shipping settings
            'base_rate' => 0.0, // base shipping cost
            'weight_rate' => 0.0, // cost per unit weight
            'free_weight_threshold' => null, // free shipping under this weight
        ];
    }

    protected function getDefaultUniqueCallback(): callable
    {
        return function ($cartableId, $cartableType, $attributes = []) {
            // Default: items are unique by model ID, type, and attributes
            $attributesString = '';
            if (! empty($attributes)) {
                ksort($attributes); // Sort attributes to ensure consistent JSON encoding
                $attributesString = json_encode($attributes);
            }

            return md5($cartableId.$cartableType.$attributesString);
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
