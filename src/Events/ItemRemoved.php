<?php

namespace HCart\LaravelMultiCart\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ItemRemoved
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $cartName,
        public string|int $itemId,
        public array $itemData,
        public int|string|null $cartableId,
        public string $cartableType,
        public array $removedItemData
    ) {}
}
