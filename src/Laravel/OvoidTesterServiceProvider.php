<?php

namespace Mrzeroc\OvoApi\Laravel;

use Illuminate\Support\ServiceProvider;

class OvoidTesterServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/ovoid.php', 'ovoid');
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'ovoid-api');
        $this->loadRoutesFrom(__DIR__.'/../../routes/web.php');

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../../config/ovoid.php' => config_path('ovoid.php'),
        ], 'ovoid-config');

        $this->publishes([
            __DIR__.'/../../resources/views' => resource_path('views/vendor/ovoid-api'),
        ], 'ovoid-views');
    }
}
