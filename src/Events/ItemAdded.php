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

    /** @var array<string, mixed> */
    public array $attributes;

    /** @var array<string, mixed> */
    public array $itemData;

    public int|string $cartableId;

    public string $cartableType;

    public float $price;

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $itemData
     */
    public function __construct(
        string $cartName,
        Model $cartable,
        int $quantity,
        array $attributes,
        array $itemData = []
    ) {
        $this->cartName = $cartName;
        $this->cartable = $cartable;
        $this->quantity = $quantity;
        $this->attributes = $attributes;
        $this->itemData = $itemData;
        $this->cartableId = $cartable->getKey();
        $this->cartableType = get_class($cartable);
        $this->price = method_exists($cartable, 'getCartPrice')
            ? $cartable->getCartPrice()
            : ($itemData['price'] ?? 0.0);
    }
}
