@props(['status'])

@php use Ikromjon\LocalNotifications\Enums\PermissionStatus; @endphp

@if ($status !== PermissionStatus::Granted->value)
    <div
        wire:poll.5s="refreshPermissionStatus"
        x-data
        x-init="window.autoRequestNotificationPermission?.()"
        class="alert alert-error mb-4 flex flex-col items-start gap-2"
    >
        <div class="flex items-center gap-2">
            <x-mary-icon name="o-bell-slash" class="shrink-0" />
            <span class="font-semibold">Notifications are disabled</span>
        </div>
        <p class="text-sm">Reminders won't be delivered. Allow notifications for this app in the system settings.</p>
        <div class="flex gap-2">
            <x-mary-button label="Open settings" class="btn-sm" wire:click="openAppSettings" />
        </div>
    </div>
@endif
