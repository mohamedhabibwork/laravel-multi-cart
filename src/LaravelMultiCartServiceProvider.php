<?php

namespace HCart\LaravelMultiCart;

use HCart\LaravelMultiCart\Commands\CartCleanupCommand;
use HCart\LaravelMultiCart\Commands\CartMigrateProviderCommand;
use HCart\LaravelMultiCart\Commands\PublishMigrationsCommand;
use HCart\LaravelMultiCart\Config\LaravelMultiCartConfig;
use HCart\LaravelMultiCart\Providers\CacheCartProvider;
use HCart\LaravelMultiCart\Providers\DatabaseCartProvider;
use HCart\LaravelMultiCart\Providers\FileCartProvider;
use HCart\LaravelMultiCart\Providers\RedisCartProvider;
use HCart\LaravelMultiCart\Providers\SessionCartProvider;
use HCart\LaravelMultiCart\Services\CartManager;
use HCart\LaravelMultiCart\Services\CartService;
use HCart\LaravelMultiCart\Services\ConfigurationService;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelMultiCartServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-multi-cart')
            ->hasConfigFile()
            ->hasMigrations([
                'create_carts_table',
                'create_cart_items_table',
            ])
            ->hasCommands([
                CartCleanupCommand::class,
                CartMigrateProviderCommand::class,
                PublishMigrationsCommand::class,
            ]);
    }

    public function packageRegistered(): void
    {
        // Register configuration
        $this->app->singleton('LaravelMultiCart.config', function ($app) {
            $configClass = config('laravel-multi-cart.config_class', LaravelMultiCartConfig::class);

            return new $configClass(config('laravel-multi-cart', []));
        });

        // Register cart providers
        $this->registerCartProviders();

        // Register services
        $this->app->singleton(CartManager::class, function ($app) {
            return new CartManager($app);
        });

        $this->app->bind(CartService::class, function ($app) {
            return new CartService($app[CartManager::class], $app['LaravelMultiCart.config']);
        });

        $this->app->singleton(ConfigurationService::class, function ($app) {
            return new ConfigurationService($app['LaravelMultiCart.config']);
        });

        // Register main cart facade binding
        $this->app->singleton('laravel-multi-cart', function ($app) {
            return new LaravelMultiCart($app);
        });
    }

    public function packageBooted(): void
    {
        // Schedule cart cleanup if enabled
        if (config('laravel-multi-cart.cleanup.enabled', true)) {
            $this->app->booted(function ($app) {
                if ($app->runningInConsole()) {
                    $schedule = $app->make(\Illuminate\Console\Scheduling\Schedule::class);
                    $schedule->command('cart:cleanup')
                        ->cron(config('laravel-multi-cart.cleanup.schedule', 'daily'));
                }
            });
        }
    }

    protected function registerCartProviders(): void
    {
        $this->app->bind('cart.provider.cache', CacheCartProvider::class);
        $this->app->bind('cart.provider.database', DatabaseCartProvider::class);
        $this->app->bind('cart.provider.session', SessionCartProvider::class);
        $this->app->bind('cart.provider.redis', RedisCartProvider::class);
        $this->app->bind('cart.provider.file', FileCartProvider::class);
    }
}
