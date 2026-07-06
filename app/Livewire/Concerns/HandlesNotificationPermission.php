<?php

namespace App\Livewire\Concerns;

use App\Services\NotificationPermission;
use Ikromjon\LocalNotifications\Enums\PermissionStatus;
use Ikromjon\LocalNotifications\Events\PermissionDenied;
use Ikromjon\LocalNotifications\Events\PermissionGranted;
use Native\Mobile\Attributes\OnNative;

/**
 * Shares the notification-permission banner state and actions between pages.
 *
 * Prompting happens client-side only (the banner's x-init fires the JS bridge
 * call in app.js). The dialog's answer arrives through the plugin's native
 * events; granting via the system Settings screen fires no event at all, so
 * the banner also polls refreshPermissionStatus() while visible.
 */
trait HandlesNotificationPermission
{
    public string $permissionStatus = PermissionStatus::Granted->value;

    public function mountHandlesNotificationPermission(): void
    {
        $this->permissionStatus = app(NotificationPermission::class)->status()->value;
    }

    public function refreshPermissionStatus(): void
    {
        $this->permissionStatus = app(NotificationPermission::class)->status()->value;
    }

    public function openAppSettings(NotificationPermission $permission): void
    {
        $permission->openAppSettings();
    }

    #[OnNative(PermissionGranted::class)]
    public function onPermissionGranted(): void
    {
        $this->permissionStatus = PermissionStatus::Granted->value;
    }

    #[OnNative(PermissionDenied::class)]
    public function onPermissionDenied(): void
    {
        $this->permissionStatus = PermissionStatus::Denied->value;
    }
}
