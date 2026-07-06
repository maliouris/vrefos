<?php

namespace App\Providers;

use App\Models\BabyAction;
use App\Services\LocalNotificationScheduler;
use Ikromjon\LocalNotifications\LocalNotificationsServiceProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\ServiceProvider;
use Native\Mobile\Providers\DeviceServiceProvider;
use Native\Mobile\Providers\SystemServiceProvider;
use Vrefos\NativeAssets\NativeAssetsServiceProvider;

class NativeServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        if (! function_exists('nativephp_call') || ! class_exists('Ikromjon\LocalNotifications\Facades\LocalNotifications')) {
            return;
        }

        // Note: the notification permission auto-prompt does NOT live here — a
        // bridge call during provider boot fires too early in the app cold
        // start for the OS to show the dialog. It is triggered post-render
        // via wire:init on the permission banner (HandlesNotificationPermission).
        if (! Cache::has('notifications_resynced_at')) {
            $scheduler = $this->app->make(LocalNotificationScheduler::class);

            BabyAction::whereNotNull('notification_scheduled_at')
                ->with(['baby', 'babyActionType'])
                ->each(fn (BabyAction $action) => $scheduler->rescheduleFor($action));

            Cache::put('notifications_resynced_at', now(), 300);
        }
    }

    /**
     * The NativePHP plugins to enable.
     *
     * Only plugins listed here will be compiled into your native builds.
     * This is a security measure to prevent transitive dependencies from
     * automatically registering plugins without your explicit consent.
     *
     * @return array<int, class-string<ServiceProvider>>
     */
    public function plugins(): array
    {
        return [
            LocalNotificationsServiceProvider::class,
            NativeAssetsServiceProvider::class,
            SystemServiceProvider::class,
            DeviceServiceProvider::class,

        ];
    }
}
