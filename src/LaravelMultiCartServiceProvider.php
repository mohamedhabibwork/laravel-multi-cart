<?php

namespace HCart\LaravelMultiCart;

use HCart\LaravelMultiCart\Commands\LaravelMultiCartCommand;
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
            ->hasViews()
            ->hasMigration('create_laravel_multi_cart_table')
            ->hasCommand(LaravelMultiCartCommand::class);
    }
}
