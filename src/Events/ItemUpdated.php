<?php

namespace HCart\LaravelMultiCart\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ItemUpdated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $cartName,
        public string|int $itemId,
        public array $oldData,
        public array $newData
    ) {}
}
