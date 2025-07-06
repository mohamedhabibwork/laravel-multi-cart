<?php

namespace HCart\LaravelMultiCart\Tests;

use HCart\LaravelMultiCart\LaravelMultiCartServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'HCart\\LaravelMultiCart\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );

        $this->setUpDatabase();
    }

    protected function getPackageProviders($app)
    {
        return [
            LaravelMultiCartServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        config()->set('cache.default', 'array');
        config()->set('session.driver', 'array');

        // Load the package configuration
        $config = require __DIR__.'/../config/laravel-multi-cart.php';
        // Override database provider to use testing connection
        $config['providers']['database']['connection'] = 'testing';
        config()->set('laravel-multi-cart', $config);
    }

    protected function setUpDatabase(): void
    {
        // Create carts table
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
            $table->softDeletesTz()->index();

            $table->index(['name']);
            $table->index(['session_id']);
            $table->index(['expires_at']);
        });

        // Create cart_items table
        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $table->nullableMorphs('cartable');
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('price', 10, 2)->default(0.00);
            $table->json('attributes')->default('{}');

            // Discount settings
            $table->json('discount_settings')->nullable();

            // Tax settings
            $table->json('tax_settings')->nullable();

            // Shipping settings
            $table->json('shipping_settings')->nullable();

            // Piece-based shipping configuration
            $table->unsignedInteger('pieces_per_shipping')->default(2);
            $table->unsignedInteger('max_shipping_charges')->nullable(); // null means unlimited

            // Calculated amounts (for performance)
            $table->decimal('discount_amount', 10, 2)->default(0.00);
            $table->decimal('tax_amount', 10, 2)->default(0.00);
            $table->decimal('shipping_amount', 10, 2)->default(0.00);

            // Total amount including all adjustments
            $table->decimal('total_amount', 10, 2)->default(0.00);
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->softDeletesTz()->index();
        });

        // Create test users table
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamps();
        });

        // Create test products table
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('price', 8, 2);
            $table->string('sku')->unique();
            $table->timestamps();
        });
    }
}
