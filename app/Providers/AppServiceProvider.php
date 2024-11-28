<?php

namespace App\Providers;

use App\Contracts\BeamsClient;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(BeamsClient::class, fn () => new \Pusher\PushNotifications\PushNotifications([
                "instanceId" => config('pusher.id'),
                "secretKey" => config('pusher.key'),
            ]
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
