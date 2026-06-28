<?php

namespace App\Providers;

use App\Models\Baby;
use App\Models\BabyAction;
use App\Observers\BabyActionObserver;
use App\Observers\BabyObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        BabyAction::observe(BabyActionObserver::class);
        Baby::observe(BabyObserver::class);
    }
}
