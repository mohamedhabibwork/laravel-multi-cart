<?php

use HCart\LaravelMultiCart\Models\Cart;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(config('laravel-multi-cart.models.cart', Cart::class), 'cart_id')->constrained()->cascadeOnDelete();
            $table->nullableMorphs('cartable');
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('price', 10, 2)->default(0.00);
            $table->json('attributes')->default('{}');
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->softDeletesTz()->index(); // For soft deletes
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_items');
    }
};
