<?php

namespace Proxynth\Larawebhook;

use Proxynth\Larawebhook\Commands\SkeletonCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LarawebhookServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package->name('larawebhook')
            ->hasConfigFile()
            ->hasMigration('create_larawebhook_table')
            ->hasCommands(SkeletonCommand::class);
    }
}
