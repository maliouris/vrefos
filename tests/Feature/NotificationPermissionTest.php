<?php

namespace Tests\Feature;

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
     * counts request() calls, mirroring the scheduler's seam-fake style.
     */
    private function fakePermission(PermissionStatus $status): NotificationPermission
    {
        $fake = new class extends NotificationPermission
        {
            public PermissionStatus $fakeStatus = PermissionStatus::Granted;

            public int $requestCalls = 0;

            public function status(): PermissionStatus
            {
                return $this->fakeStatus;
            }

            public function request(): void
            {
                $this->requestCalls++;
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

    public function test_mount_with_granted_permission_shows_no_banner(): void
    {
        $this->fakePermission(PermissionStatus::Granted);

        Livewire::test(Index::class)
            ->assertSet('permissionStatus', 'granted')
            ->assertDontSee('Notifications are not enabled')
            ->assertDontSee('Notifications are blocked');
    }

    public function test_mount_with_undetermined_permission_prompts_and_shows_banner(): void
    {
        $fake = $this->fakePermission(PermissionStatus::NotDetermined);

        Livewire::test(Index::class)
            ->assertSet('permissionStatus', 'not_determined')
            ->assertSee('Notifications are not enabled')
            ->assertSee('Allow notifications');

        $this->assertSame(1, $fake->requestCalls);
    }

    public function test_mount_with_denied_permission_shows_blocked_banner_without_prompting(): void
    {
        $fake = $this->fakePermission(PermissionStatus::Denied);

        Livewire::test(Index::class)
            ->assertSet('permissionStatus', 'denied')
            ->assertSee('Notifications are blocked')
            ->assertSee('Try again')
            ->assertSee('Open settings');

        $this->assertSame(0, $fake->requestCalls);
    }

    public function test_request_permission_action_invokes_service_and_refreshes_status(): void
    {
        $fake = $this->fakePermission(PermissionStatus::Denied);

        $component = Livewire::test(Index::class);

        $fake->fakeStatus = PermissionStatus::Granted;

        $component->call('requestPermission')
            ->assertSet('permissionStatus', 'granted')
            ->assertDontSee('Notifications are blocked');

        $this->assertSame(1, $fake->requestCalls);
    }

    public function test_permission_granted_event_clears_the_banner(): void
    {
        $this->fakePermission(PermissionStatus::NotDetermined);

        Livewire::test(Index::class)
            ->assertSee('Notifications are not enabled')
            ->call('onPermissionGranted')
            ->assertSet('permissionStatus', 'granted')
            ->assertDontSee('Notifications are not enabled');
    }

    public function test_permission_denied_event_switches_to_blocked_banner(): void
    {
        $this->fakePermission(PermissionStatus::NotDetermined);

        Livewire::test(Index::class)
            ->call('onPermissionDenied')
            ->assertSet('permissionStatus', 'denied')
            ->assertSee('Notifications are blocked');
    }
}
