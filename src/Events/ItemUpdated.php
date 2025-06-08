<?php

namespace HCart\LaravelMultiCart\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ItemUpdated
{
    use Dispatchable, SerializesModels;

    public string $cartName;

    public string|int $itemId;

    public array $oldData;

    public array $newData;

    public function __construct(string $cartName, string|int $itemId, array $oldData, array $newData)
    {
        $this->cartName = $cartName;
        $this->itemId = $itemId;
        $this->oldData = $oldData;
        $this->newData = $newData;
    }
}
