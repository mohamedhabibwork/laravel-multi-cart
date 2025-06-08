<?php

namespace HCart\LaravelMultiCart\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ItemAdded
{
    use Dispatchable, SerializesModels;

    public string $cartName;

    public Model $cartable;

    public int $quantity;

    public array $attributes;

    public int $cartableId;

    public string $cartableType;

    public float $price;

    public array $itemData;

    public function __construct(string $cartName, Model $cartable, int $quantity, array $attributes, array $itemData = [])
    {
        $this->cartName = $cartName;
        $this->cartable = $cartable;
        $this->quantity = $quantity;
        $this->attributes = $attributes;
        $this->cartableId = $cartable->getKey();
        $this->cartableType = get_class($cartable);
        $this->price = method_exists($cartable, 'getCartPrice') ? $cartable->getCartPrice() : (float) $cartable->price;
        $this->itemData = $itemData;
    }
}
