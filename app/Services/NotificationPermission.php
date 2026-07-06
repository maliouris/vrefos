<?php

namespace App\Services;

use Ikromjon\LocalNotifications\Enums\PermissionStatus;
use Ikromjon\LocalNotifications\Facades\LocalNotifications;
use Native\Mobile\Facades\System;

class NotificationPermission
{
    public function status(): PermissionStatus
    {
        $status = $this->dispatchCheck()['status'] ?? null;

        return PermissionStatus::tryFrom((string) $status) ?? PermissionStatus::Granted;
    }

    public function isGranted(): bool
    {
        return $this->status() === PermissionStatus::Granted;
    }

    public function openAppSettings(): void
    {
        $this->dispatchOpenSettings();
    }

    /**
     * Ask the native plugin for the current permission status.
     *
     * Guarded so it is a no-op on web/tests (no native runtime, where the empty
     * result resolves to Granted); extracted as a seam for testing.
     *
     * @return array<string, mixed>
     */
    protected function dispatchCheck(): array
    {
        if (function_exists('nativephp_call') && class_exists(LocalNotifications::class)) {
            return LocalNotifications::checkPermission();
        }

        return [];
    }

    /**
     * Open the app's settings screen in the device settings, where the user
     * can enable notifications after the OS has permanently blocked the
     * permission dialog (Android does so after two denials). The native
     * handler for System.OpenAppSettings ships with nativephp/mobile-system.
     *
     * Guarded so it is a no-op on web/tests; extracted as a seam for testing.
     */
    protected function dispatchOpenSettings(): void
    {
        if (function_exists('nativephp_call') && class_exists(System::class)) {
            System::appSettings();
        }
    }
}
