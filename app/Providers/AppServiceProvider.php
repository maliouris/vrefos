<?php

namespace App\Providers;

use App\Contracts\BeamsClient;
use App\Contracts\PushNotifications;
use App\Services\BeamsNotificationsService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(BeamsClient::class, fn () => new BeamsClient([
                "instanceId" => config('pusher.id'),
                "secretKey" => config('pusher.key'),
            ]
        ));

        $this->app->singleton(PushNotifications::class, BeamsNotificationsService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
