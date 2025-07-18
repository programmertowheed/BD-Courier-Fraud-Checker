<?php

namespace Programmertowheed\BdCourierFraudChecker;

use Illuminate\Support\ServiceProvider;
use Programmertowheed\BdCourierFraudChecker\Courier\Pathao;
use Programmertowheed\BdCourierFraudChecker\Courier\Redx;
use Programmertowheed\BdCourierFraudChecker\Courier\Steadfast;
use Programmertowheed\BdCourierFraudChecker\Services\CourierCheckerService;

class BdCourierFraudCheckerServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../config/bdcourierfraudchecker.php' => config_path('bdcourierfraudchecker.php'),
        ], 'bdcourierfraudchecker-config');
    }

    /**
     * Register application services
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . "/../config/bdcourierfraudchecker.php", "bdcourierfraudchecker");

        $this->app->singleton(CourierCheckerService::class, function ($app) {
            return new CourierCheckerService(
                $app->make(Steadfast::class),
                $app->make(Pathao::class),
                $app->make(Redx::class)
            );
        });

        $this->app->alias(CourierCheckerService::class, 'bd-courier-fraud-checker');
    }
}
