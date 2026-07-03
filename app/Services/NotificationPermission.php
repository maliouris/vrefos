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

    public function request(): void
    {
        $this->dispatchRequest();
    }

    public function openAppSettings(): void
    {
        if (function_exists('nativephp_call') && class_exists(System::class)) {
            System::appSettings();
        }
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
     * Trigger the OS permission dialog via the native plugin. The user's answer
     * arrives asynchronously through the PermissionGranted / PermissionDenied
     * native events.
     *
     * Guarded so it is a no-op on web/tests; extracted as a seam for testing.
     */
    protected function dispatchRequest(): void
    {
        if (function_exists('nativephp_call') && class_exists(LocalNotifications::class)) {
            LocalNotifications::requestPermission();
        }
    }
}
