<?php

namespace HCart\LaravelMultiCart\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ItemRemoved
{
    use Dispatchable, SerializesModels;

    public string $cartName;

    public string|int $itemId;

    public array $itemData;

    public int $cartableId;

    public string $cartableType;

    public array $removedItemData;

    public function __construct(string $cartName, string|int $itemId, array $itemData)
    {
        $this->cartName = $cartName;
        $this->itemId = $itemId;
        $this->itemData = $itemData;
        $this->cartableId = $itemData['cartable_id'];
        $this->cartableType = $itemData['cartable_type'];
        $this->removedItemData = $itemData;
    }
}
