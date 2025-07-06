<?php

namespace HCart\LaravelMultiCart\Models;

use HCart\LaravelMultiCart\Contracts\CartItemInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CartItem extends Model implements CartItemInterface
{
    protected $fillable = [
        'cart_id',
        'cartable_id',
        'cartable_type',
        'quantity',
        'price',
        'attributes',
        'discount_settings',
        'tax_settings',
        'shipping_settings',
        'discount_amount',
        'tax_amount',
        'shipping_amount',
        'total_amount',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'price' => 'decimal:2',
        'attributes' => 'array',
        'discount_settings' => 'array',
        'tax_settings' => 'array',
        'shipping_settings' => 'array',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'shipping_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function cart(): BelongsTo
    {
        $cartModel = app('LaravelMultiCart.config')->getCartModel();

        return $this->belongsTo($cartModel);
    }

    public function cartable(): MorphTo
    {
        return $this->morphTo();
    }

    public function getUniqueKey(): string
    {
        $callback = app('LaravelMultiCart.config')->getUniqueItemCallback();

        if ($callback && $value = $callback($this->cartable_id, $this->cartable_type, $this->attributes)) {
            return $value;
        }

        return md5($this->cartable_id.$this->cartable_type);
    }

    public function getTotal(): float
    {
        return (float) ($this->price * $this->quantity);
    }

    public function getItemAttribute(string $key, $default = null)
    {
        // Use the 'attributes' column which is cast to array
        $attributes = $this->getAttribute('attributes') ?? [];

        return data_get($attributes, $key, $default);
    }

    public function setItemAttribute(string $key, $value): void
    {
        // Use the 'attributes' column which is cast to array
        $attributes = $this->getAttribute('attributes') ?? [];
        data_set($attributes, $key, $value);
        $this->setAttribute('attributes', $attributes);
    }

    public function hasItemAttribute(string $key): bool
    {
        // Use the 'attributes' column which is cast to array
        $attributes = $this->getAttribute('attributes') ?? [];

        return data_get($attributes, $key) !== null;
    }

    public function scopeByCartable($query, $cartableId, string $cartableType)
    {
        return $query->where('cartable_id', $cartableId)
            ->where('cartable_type', $cartableType);
    }

    /**
     * Set discount settings for this item
     */
    public function setDiscountSettings(array $settings): self
    {
        $this->discount_settings = $settings;
        $this->recalculateAmounts();

        return $this;
    }

    /**
     * Set tax settings for this item
     */
    public function setTaxSettings(array $settings): self
    {
        $this->tax_settings = $settings;
        $this->recalculateAmounts();

        return $this;
    }

    /**
     * Set shipping settings for this item
     */
    public function setShippingSettings(array $settings): self
    {
        $this->shipping_settings = $settings;
        $this->recalculateAmounts();

        return $this;
    }

    /**
     * Calculate discount amount for this item
     */
    public function calculateDiscountAmount(): float
    {
        $settings = $this->discount_settings ?? [];

        if (! ($settings['enabled'] ?? true)) {
            return 0.0;
        }

        $baseAmount = $this->price * $this->quantity;
        $discountValue = $settings['value'] ?? 0.0;
        $discountType = $settings['type'] ?? 'percentage';

        if ($discountType === 'percentage') {
            $discountAmount = $baseAmount * ($discountValue / 100);
        } else {
            $discountAmount = $discountValue * $this->quantity;
        }

        // Apply minimum and maximum limits
        if (isset($settings['minimum_amount']) && $discountAmount < $settings['minimum_amount']) {
            $discountAmount = $settings['minimum_amount'];
        }

        if (isset($settings['maximum_amount']) && $discountAmount > $settings['maximum_amount']) {
            $discountAmount = $settings['maximum_amount'];
        }

        return (float) $discountAmount;
    }

    /**
     * Calculate tax amount for this item
     */
    public function calculateTaxAmount(): float
    {
        $settings = $this->tax_settings ?? [];

        if (! ($settings['enabled'] ?? true)) {
            return 0.0;
        }

        $baseAmount = $this->price * $this->quantity;
        $taxValue = $settings['value'] ?? 0.0;
        $taxType = $settings['type'] ?? 'percentage';

        // If tax is compound, calculate on amount after discount
        if ($settings['compound'] ?? false) {
            $baseAmount -= $this->calculateDiscountAmount();
        }

        if ($taxType === 'percentage') {
            $taxAmount = $baseAmount * ($taxValue / 100);
        } else {
            $taxAmount = $taxValue * $this->quantity;
        }

        return (float) $taxAmount;
    }

    /**
     * Calculate shipping amount for this item
     */
    public function calculateShippingAmount(): float
    {
        $settings = $this->shipping_settings ?? [];

        if (! ($settings['enabled'] ?? true)) {
            return 0.0;
        }

        $baseAmount = $this->price * $this->quantity;
        $shippingValue = $settings['value'] ?? 0.0;
        $shippingType = $settings['type'] ?? 'fixed';

        if ($shippingType === 'percentage') {
            $shippingAmount = $baseAmount * ($shippingValue / 100);
        } else {
            $shippingAmount = $shippingValue * $this->quantity;
        }

        return (float) $shippingAmount;
    }

    /**
     * Recalculate all amounts based on current settings
     */
    public function recalculateAmounts(): self
    {
        $this->discount_amount = $this->calculateDiscountAmount();
        $this->tax_amount = $this->calculateTaxAmount();
        $this->shipping_amount = $this->calculateShippingAmount();

        $this->total_amount = ($this->price * $this->quantity)
            - $this->discount_amount
            + $this->tax_amount
            + $this->shipping_amount;

        return $this;
    }

    /**
     * Get total with all adjustments
     */
    public function getTotalWithAdjustments(): float
    {
        return (float) $this->total_amount;
    }

    /**
     * Get item subtotal (price * quantity)
     */
    public function getSubtotal(): float
    {
        return (float) ($this->price * $this->quantity);
    }

    /**
     * Get discount amount
     */
    public function getDiscountAmount(): float
    {
        return (float) $this->discount_amount;
    }

    /**
     * Get tax amount
     */
    public function getTaxAmount(): float
    {
        return (float) $this->tax_amount;
    }

    /**
     * Get shipping amount
     */
    public function getShippingAmount(): float
    {
        return (float) $this->shipping_amount;
    }

    /**
     * Check if discount is included in price
     */
    public function isDiscountIncluded(): bool
    {
        return $this->discount_settings['included'] ?? false;
    }

    /**
     * Check if tax is included in price
     */
    public function isTaxIncluded(): bool
    {
        return $this->tax_settings['included'] ?? false;
    }

    /**
     * Check if shipping is included in price
     */
    public function isShippingIncluded(): bool
    {
        return $this->shipping_settings['included'] ?? false;
    }
}
