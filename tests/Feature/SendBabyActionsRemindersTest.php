<?php

namespace Tests\Feature;

use App\Contracts\PushNotifications;
use App\Enums\NotifyFrom;
use App\Models\Baby;
use App\Models\BabyAction;
use App\Models\BabyActionType;
use App\Models\NotificationSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SendBabyActionsRemindersTest extends TestCase
{
    use RefreshDatabase;

    private Baby $baby;

    protected function setUp(): void
    {
        parent::setUp();

        $this->baby = Baby::factory()->create();
    }

    public function test_sends_reminder_for_eat_action_when_notify_from_started_at_and_threshold_reached(): void
    {
        $actionType = BabyActionType::create(['name' => 'Eat']);
        $action = BabyAction::create([
            'baby_id' => $this->baby->id,
            'baby_action_type_id' => $actionType->id,
            'started_at' => now()->subMinutes(200),
            'finished_at' => null,
        ]);
        NotificationSetting::create([
            'baby_action_type_id' => $actionType->id,
            'enabled' => true,
            'notify_after_minutes' => 180,
            'notify_from' => NotifyFrom::StartedAt,
        ]);

        $mock = $this->mock(PushNotifications::class);
        $mock->shouldReceive('send')->once();

        $this->artisan('app:send-baby-action-reminder');

        $this->assertEquals(1, $action->fresh()->reminders);
    }

    public function test_sends_reminder_for_sleep_action_when_notify_from_started_at_and_threshold_reached(): void
    {
        $actionType = BabyActionType::create(['name' => 'Sleep']);
        $action = BabyAction::create([
            'baby_id' => $this->baby->id,
            'baby_action_type_id' => $actionType->id,
            'started_at' => now()->subMinutes(200),
            'finished_at' => null,
        ]);
        NotificationSetting::create([
            'baby_action_type_id' => $actionType->id,
            'enabled' => true,
            'notify_after_minutes' => 180,
            'notify_from' => NotifyFrom::StartedAt,
        ]);

        $mock = $this->mock(PushNotifications::class);
        $mock->shouldReceive('send')->once();

        $this->artisan('app:send-baby-action-reminder');

        $this->assertEquals(1, $action->fresh()->reminders);
    }

    public function test_does_not_send_reminder_when_notify_from_started_at_and_threshold_not_reached(): void
    {
        $actionType = BabyActionType::create(['name' => 'Eat']);
        $action = BabyAction::create([
            'baby_id' => $this->baby->id,
            'baby_action_type_id' => $actionType->id,
            'started_at' => now()->subMinutes(120),
            'finished_at' => null,
        ]);
        NotificationSetting::create([
            'baby_action_type_id' => $actionType->id,
            'enabled' => true,
            'notify_after_minutes' => 180,
            'notify_from' => NotifyFrom::StartedAt,
        ]);

        $mock = $this->mock(PushNotifications::class);
        $mock->shouldNotReceive('send');

        $this->artisan('app:send-baby-action-reminder');

        $this->assertEquals(0, $action->fresh()->reminders);
    }

    public function test_sends_reminder_when_notify_from_finished_at_and_threshold_reached(): void
    {
        $actionType = BabyActionType::create(['name' => 'Eat']);
        $action = BabyAction::create([
            'baby_id' => $this->baby->id,
            'baby_action_type_id' => $actionType->id,
            'started_at' => now()->subMinutes(300),
            'finished_at' => now()->subMinutes(200),
        ]);
        NotificationSetting::create([
            'baby_action_type_id' => $actionType->id,
            'enabled' => true,
            'notify_after_minutes' => 180,
            'notify_from' => NotifyFrom::FinishedAt,
        ]);

        $mock = $this->mock(PushNotifications::class);
        $mock->shouldReceive('send')->once();

        $this->artisan('app:send-baby-action-reminder');

        $this->assertEquals(1, $action->fresh()->reminders);
    }

    public function test_does_not_send_reminder_when_notify_from_finished_at_and_threshold_not_reached(): void
    {
        $actionType = BabyActionType::create(['name' => 'Eat']);
        $action = BabyAction::create([
            'baby_id' => $this->baby->id,
            'baby_action_type_id' => $actionType->id,
            'started_at' => now()->subMinutes(300),
            'finished_at' => now()->subMinutes(120),
        ]);
        NotificationSetting::create([
            'baby_action_type_id' => $actionType->id,
            'enabled' => true,
            'notify_after_minutes' => 180,
            'notify_from' => NotifyFrom::FinishedAt,
        ]);

        $mock = $this->mock(PushNotifications::class);
        $mock->shouldNotReceive('send');

        $this->artisan('app:send-baby-action-reminder');

        $this->assertEquals(0, $action->fresh()->reminders);
    }

    public function test_does_not_send_reminder_when_notify_from_finished_at_but_action_not_finished(): void
    {
        $actionType = BabyActionType::create(['name' => 'Eat']);
        $action = BabyAction::create([
            'baby_id' => $this->baby->id,
            'baby_action_type_id' => $actionType->id,
            'started_at' => now()->subMinutes(300),
            'finished_at' => null,
        ]);
        NotificationSetting::create([
            'baby_action_type_id' => $actionType->id,
            'enabled' => true,
            'notify_after_minutes' => 180,
            'notify_from' => NotifyFrom::FinishedAt,
        ]);

        $mock = $this->mock(PushNotifications::class);
        $mock->shouldNotReceive('send');

        $this->artisan('app:send-baby-action-reminder');

        $this->assertEquals(0, $action->fresh()->reminders);
    }

    public function test_notify_after_minutes_is_respected_for_started_at(): void
    {
        $actionType = BabyActionType::create(['name' => 'Eat']);

        $tooSoon = BabyAction::create([
            'baby_id' => $this->baby->id,
            'baby_action_type_id' => $actionType->id,
            'started_at' => now()->subMinutes(59),
            'finished_at' => null,
        ]);
        $justRight = BabyAction::create([
            'baby_id' => $this->baby->id,
            'baby_action_type_id' => $actionType->id,
            'started_at' => now()->subMinutes(61),
            'finished_at' => null,
        ]);
        NotificationSetting::create([
            'baby_action_type_id' => $actionType->id,
            'enabled' => true,
            'notify_after_minutes' => 60,
            'notify_from' => NotifyFrom::StartedAt,
        ]);

        $mock = $this->mock(PushNotifications::class);
        $mock->shouldReceive('send')->once();

        $this->artisan('app:send-baby-action-reminder');

        $this->assertEquals(0, $tooSoon->fresh()->reminders);
        $this->assertEquals(1, $justRight->fresh()->reminders);
    }

    public function test_notify_after_minutes_is_respected_for_finished_at(): void
    {
        $actionType = BabyActionType::create(['name' => 'Sleep']);

        $tooSoon = BabyAction::create([
            'baby_id' => $this->baby->id,
            'baby_action_type_id' => $actionType->id,
            'started_at' => now()->subMinutes(200),
            'finished_at' => now()->subMinutes(59),
        ]);
        $justRight = BabyAction::create([
            'baby_id' => $this->baby->id,
            'baby_action_type_id' => $actionType->id,
            'started_at' => now()->subMinutes(200),
            'finished_at' => now()->subMinutes(61),
        ]);
        NotificationSetting::create([
            'baby_action_type_id' => $actionType->id,
            'enabled' => true,
            'notify_after_minutes' => 60,
            'notify_from' => NotifyFrom::FinishedAt,
        ]);

        $mock = $this->mock(PushNotifications::class);
        $mock->shouldReceive('send')->once();

        $this->artisan('app:send-baby-action-reminder');

        $this->assertEquals(0, $tooSoon->fresh()->reminders);
        $this->assertEquals(1, $justRight->fresh()->reminders);
    }

    public function test_does_not_send_reminder_when_notifications_disabled(): void
    {
        $actionType = BabyActionType::create(['name' => 'Eat']);
        $action = BabyAction::create([
            'baby_id' => $this->baby->id,
            'baby_action_type_id' => $actionType->id,
            'started_at' => now()->subMinutes(200),
            'finished_at' => null,
        ]);
        NotificationSetting::create([
            'baby_action_type_id' => $actionType->id,
            'enabled' => false,
            'notify_after_minutes' => 180,
            'notify_from' => NotifyFrom::StartedAt,
        ]);

        $mock = $this->mock(PushNotifications::class);
        $mock->shouldNotReceive('send');

        $this->artisan('app:send-baby-action-reminder');

        $this->assertEquals(0, $action->fresh()->reminders);
    }
}
