<?php

namespace HCart\LaravelMultiCart\Models;

use HCart\LaravelMultiCart\Contracts\CartShippableInterface;
use HCart\LaravelMultiCart\Contracts\CartTaxableInterface;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cart extends Model implements CartShippableInterface, CartTaxableInterface
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'config',
        'user_id',
        'user_type',
        'session_id',
        'expires_at',
    ];

    protected $casts = [
        'config' => 'array',
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        $cartItemModel = app('LaravelMultiCart.config')->getCartItemModel();

        return $this->hasMany($cartItemModel);
    }

    public function user(): MorphTo
    {
        return $this->morphTo();
    }

    protected function config(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ? json_decode($value, true) : [],
            set: fn (array $value) => json_encode($value)
        );
    }

    public function getConfigValue(string $key, $default = null)
    {
        return data_get($this->config, $key, $default);
    }

    public function setConfigValue(string $key, $value): void
    {
        $config = $this->config;
        data_set($config, $key, $value);
        $this->config = $config;
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
        });
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForSession($query, string $sessionId)
    {
        return $query->where('session_id', $sessionId);
    }

    // CartTaxableInterface implementation
    public function getCartTaxSettings(): array
    {
        return $this->getConfigValue('tax_settings', []);
    }

    public function getCartTaxRate(): float
    {
        $settings = $this->getCartTaxSettings();

        return (float) ($settings['value'] ?? 0.0);
    }

    public function getCartTaxType(): string
    {
        $settings = $this->getCartTaxSettings();

        return $settings['type'] ?? 'percentage';
    }

    public function isCartTaxEnabled(): bool
    {
        $settings = $this->getCartTaxSettings();

        return $settings['enabled'] ?? true;
    }

    public function isCartTaxCompound(): bool
    {
        $settings = $this->getCartTaxSettings();

        return $settings['compound'] ?? false;
    }

    public function getCartTaxCategory(): ?string
    {
        $settings = $this->getCartTaxSettings();

        return $settings['category'] ?? null;
    }

    public function getCartTaxExemptions(): array
    {
        $settings = $this->getCartTaxSettings();

        return $settings['exemptions'] ?? [];
    }

    public function isCartTaxExempt(): bool
    {
        $settings = $this->getCartTaxSettings();

        return $settings['exempt'] ?? false;
    }

    // CartShippableInterface implementation
    public function getCartShippingSettings(): array
    {
        return $this->getConfigValue('shipping_settings', []);
    }

    public function getCartShippingCost(): float
    {
        $settings = $this->getCartShippingSettings();

        return (float) ($settings['value'] ?? 0.0);
    }

    public function getCartShippingType(): string
    {
        $settings = $this->getCartShippingSettings();

        return $settings['type'] ?? 'fixed';
    }

    public function isCartShippingEnabled(): bool
    {
        $settings = $this->getCartShippingSettings();

        return $settings['enabled'] ?? true;
    }

    public function getCartShippingWeight(): float
    {
        $settings = $this->getCartShippingSettings();

        return (float) ($settings['weight'] ?? 0.0);
    }

    public function getCartShippingDimensions(): array
    {
        $settings = $this->getCartShippingSettings();

        return $settings['dimensions'] ?? [];
    }

    public function getCartShippingClass(): ?string
    {
        $settings = $this->getCartShippingSettings();

        return $settings['class'] ?? null;
    }

    public function getCartShippingZones(): array
    {
        $settings = $this->getCartShippingSettings();

        return $settings['zones'] ?? [];
    }

    public function getCartPieceBasedShippingConfig(): array
    {
        $settings = $this->getCartShippingSettings();

        return [
            'pieces_per_charge' => $settings['pieces_per_shipping'] ?? 1,
            'charge_per_group' => $settings['value'] ?? 0.0,
            'max_charges' => $settings['max_shipping_charges'] ?? null,
        ];
    }

    public function getCartPiecesPerShipping(): int
    {
        $settings = $this->getCartShippingSettings();

        return (int) ($settings['pieces_per_shipping'] ?? 1);
    }

    public function getCartMaxShippingCharges(): ?int
    {
        $settings = $this->getCartShippingSettings();

        return $settings['max_shipping_charges'] ?? null;
    }

    public function cartQualifiesForFreeShipping(float $cartTotal): bool
    {
        $settings = $this->getCartShippingSettings();
        $threshold = $settings['free_shipping_threshold'] ?? null;

        return $threshold !== null && $cartTotal >= $threshold;
    }

    public function getCartShippingExemptions(): array
    {
        $settings = $this->getCartShippingSettings();

        return $settings['exemptions'] ?? [];
    }

    public function isCartShippingExempt(): bool
    {
        $settings = $this->getCartShippingSettings();

        return $settings['exempt'] ?? false;
    }
}
