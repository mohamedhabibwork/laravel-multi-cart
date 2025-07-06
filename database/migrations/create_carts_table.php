<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->json('config')->default('{}');
            $table->nullableMorphs('user');
            $table->string('session_id')->nullable();
            $table->timestampTz('expires_at')->nullable();

            // Cart-level tax settings
            $table->json('tax_settings')->nullable();

            // Cart-level shipping settings
            $table->json('shipping_settings')->nullable();

            // Cart-level discount settings
            $table->json('discount_settings')->nullable();

            // Calculated amounts for cart (for performance)
            $table->decimal('cart_tax_amount', 10, 2)->default(0.00);
            $table->decimal('cart_shipping_amount', 10, 2)->default(0.00);
            $table->decimal('cart_discount_amount', 10, 2)->default(0.00);
            $table->decimal('cart_total_amount', 10, 2)->default(0.00);

            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->softDeletesTz()->index(); // For soft deletes

            $table->index(['name']);
            $table->index(['session_id']);
            $table->index(['expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carts');
    }
};
