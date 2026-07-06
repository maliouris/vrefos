<?php

namespace Tests\Feature;

use App\Livewire\Pages\Dashboard\Index as DashboardIndex;
use App\Livewire\Pages\NotificationSettings\Index;
use App\Services\NotificationPermission;
use Ikromjon\LocalNotifications\Enums\PermissionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class NotificationPermissionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Bind a fake NotificationPermission that reports a canned status and
     * counts openAppSettings() calls, mirroring the scheduler's seam-fake style.
     */
    private function fakePermission(PermissionStatus $status): NotificationPermission
    {
        $fake = new class extends NotificationPermission
        {
            public PermissionStatus $fakeStatus = PermissionStatus::Granted;

            public int $openSettingsCalls = 0;

            public function status(): PermissionStatus
            {
                return $this->fakeStatus;
            }

            public function openAppSettings(): void
            {
                $this->openSettingsCalls++;
            }
        };

        $fake->fakeStatus = $status;

        $this->app->instance(NotificationPermission::class, $fake);

        return $fake;
    }

    public function test_off_device_status_defaults_to_granted(): void
    {
        $this->assertSame(PermissionStatus::Granted, app(NotificationPermission::class)->status());
        $this->assertTrue(app(NotificationPermission::class)->isGranted());
    }

    public function test_granted_permission_shows_no_banner(): void
    {
        $this->fakePermission(PermissionStatus::Granted);

        Livewire::test(Index::class)
            ->assertSet('permissionStatus', 'granted')
            ->assertDontSee('Notifications are disabled');
    }

    public function test_undetermined_permission_shows_the_banner_with_auto_prompt_hook(): void
    {
        $this->fakePermission(PermissionStatus::NotDetermined);

        Livewire::test(Index::class)
            ->assertSet('permissionStatus', 'not_determined')
            ->assertSee('Notifications are disabled')
            ->assertSee('Open settings')
            ->assertDontSee('Try again')
            ->assertSeeHtml('autoRequestNotificationPermission')
            ->assertSeeHtml('wire:poll.5s="refreshPermissionStatus"');
    }

    public function test_denied_permission_shows_the_same_banner(): void
    {
        $this->fakePermission(PermissionStatus::Denied);

        Livewire::test(Index::class)
            ->assertSet('permissionStatus', 'denied')
            ->assertSee('Notifications are disabled')
            ->assertSee('Open settings')
            ->assertDontSee('Try again');
    }

    public function test_refresh_action_updates_the_status_and_clears_the_banner(): void
    {
        $fake = $this->fakePermission(PermissionStatus::Denied);

        $component = Livewire::test(Index::class)
            ->assertSee('Notifications are disabled');

        // Simulate the user enabling notifications in the system settings:
        // no native event fires, so the banner's poll picks up the change.
        $fake->fakeStatus = PermissionStatus::Granted;

        $component->call('refreshPermissionStatus')
            ->assertSet('permissionStatus', 'granted')
            ->assertDontSee('Notifications are disabled');
    }

    public function test_open_settings_action_invokes_the_service(): void
    {
        $fake = $this->fakePermission(PermissionStatus::Denied);

        Livewire::test(Index::class)->call('openAppSettings');

        $this->assertSame(1, $fake->openSettingsCalls);
    }

    public function test_permission_granted_event_clears_the_banner(): void
    {
        $this->fakePermission(PermissionStatus::NotDetermined);

        Livewire::test(Index::class)
            ->assertSee('Notifications are disabled')
            ->call('onPermissionGranted')
            ->assertSet('permissionStatus', 'granted')
            ->assertDontSee('Notifications are disabled');
    }

    public function test_permission_denied_event_keeps_the_banner(): void
    {
        $this->fakePermission(PermissionStatus::NotDetermined);

        Livewire::test(Index::class)
            ->call('onPermissionDenied')
            ->assertSet('permissionStatus', 'denied')
            ->assertSee('Notifications are disabled');
    }

    public function test_dashboard_shows_the_banner_when_not_granted(): void
    {
        $this->fakePermission(PermissionStatus::Denied);

        Livewire::test(DashboardIndex::class)
            ->assertSet('permissionStatus', 'denied')
            ->assertSee('Notifications are disabled')
            ->assertSee('Open settings')
            ->assertDontSee('Try again')
            ->assertSeeHtml('autoRequestNotificationPermission')
            ->assertSeeHtml('wire:poll.5s="refreshPermissionStatus"');
    }

    public function test_dashboard_shows_no_banner_when_granted(): void
    {
        $this->fakePermission(PermissionStatus::Granted);

        Livewire::test(DashboardIndex::class)
            ->assertDontSee('Notifications are disabled');
    }

    public function test_dashboard_permission_granted_event_clears_the_banner(): void
    {
        $this->fakePermission(PermissionStatus::Denied);

        Livewire::test(DashboardIndex::class)
            ->assertSee('Notifications are disabled')
            ->call('onPermissionGranted')
            ->assertSet('permissionStatus', 'granted')
            ->assertDontSee('Notifications are disabled');
    }
}
