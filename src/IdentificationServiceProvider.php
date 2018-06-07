<?php

namespace Msol\Identification;

use Illuminate\Support\ServiceProvider;

class IdentificationServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        //
        $this->app->singleton(IdentificationMiddleware::class, function () {
            return new IdentificationMiddleware();
        });

        $this->app->alias(IdentificationMiddleware::class, 'identification-middleware');
    }
}
