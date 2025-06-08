<?php

namespace HCart\LaravelMultiCart\Tests\Fixtures;

use HCart\LaravelMultiCart\Traits\Cartable;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use Cartable;

    protected $fillable = ['name', 'price', 'sku'];

    protected $table = 'products';

    protected $casts = [
        'price' => 'decimal:2',
    ];

    public function getCartPrice(): float
    {
        return (float) $this->price;
    }

    public function getCartName(): string
    {
        return $this->name;
    }

    public function getCartAttributes(): array
    {
        return [
            'sku' => $this->sku,
        ];
    }
}
