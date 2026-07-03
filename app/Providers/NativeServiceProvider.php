<?php

namespace App\Providers;

use App\Models\BabyAction;
use App\Services\LocalNotificationScheduler;
use App\Services\NotificationPermission;
use Ikromjon\LocalNotifications\Enums\PermissionStatus;
use Ikromjon\LocalNotifications\LocalNotificationsServiceProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\ServiceProvider;

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

        $permission = $this->app->make(NotificationPermission::class);

        // Only prompt users who haven't decided yet; a denied user is offered
        // recovery on the Notification Settings page instead of being nagged.
        if ($permission->status() === PermissionStatus::NotDetermined) {
            $permission->request();
        }

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
        ];
    }
}
