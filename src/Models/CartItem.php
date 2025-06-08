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
    ];

    protected $casts = [
        'quantity' => 'integer',
        'price' => 'decimal:2',
        'attributes' => 'array',
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

        if ($callback) {
            return $callback($this->cartable_id, $this->cartable_type, $this->attributes);
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
}
