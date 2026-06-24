<?php

namespace Tests\Feature;

use App\Enums\NotifyFrom;
use App\Models\Baby;
use App\Models\BabyAction;
use App\Models\BabyActionType;
use App\Models\NotificationSetting;
use App\Services\LocalNotificationScheduler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocalNotificationSchedulerTest extends TestCase
{
    use RefreshDatabase;

    private LocalNotificationScheduler $scheduler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scheduler = app(LocalNotificationScheduler::class);
    }

    private function settingFor(BabyActionType $type, array $overrides = []): NotificationSetting
    {
        return NotificationSetting::create(array_merge([
            'baby_action_type_id' => $type->id,
            'enabled' => true,
            'notify_after_minutes' => 180,
            'notify_from' => NotifyFrom::StartedAt,
        ], $overrides));
    }

    public function test_schedule_for_with_valid_times_sets_notification_scheduled_at(): void
    {
        $baby = Baby::factory()->create();
        $actionType = BabyActionType::factory()->create();
        $this->settingFor($actionType);

        $action = BabyAction::factory()
            ->for($baby)
            ->create([
                'baby_action_type_id' => $actionType->id,
                'started_at' => now()->subHours(1),
                'finished_at' => null,
            ]);

        $result = $this->scheduler->scheduleFor($action);

        $this->assertTrue($result);
        $action->refresh();
        $this->assertNotNull($action->notification_scheduled_at);
        $this->assertCount(1, $action->scheduled_notification_keys);
    }

    public function test_schedule_for_with_finished_at_null_returns_false(): void
    {
        $baby = Baby::factory()->create();
        $actionType = BabyActionType::factory()->create();
        $this->settingFor($actionType, ['notify_from' => NotifyFrom::FinishedAt]);

        $action = BabyAction::factory()
            ->for($baby)
            ->create([
                'baby_action_type_id' => $actionType->id,
                'started_at' => now()->subHours(1),
                'finished_at' => null,
            ]);

        $result = $this->scheduler->scheduleFor($action);

        $this->assertFalse($result);
        $action->refresh();
        $this->assertNull($action->notification_scheduled_at);
        $this->assertNull($action->scheduled_notification_keys);
    }

    public function test_schedule_for_with_fire_at_in_past_returns_false(): void
    {
        $baby = Baby::factory()->create();
        $actionType = BabyActionType::factory()->create();
        $this->settingFor($actionType, ['notify_after_minutes' => 1]);

        $action = BabyAction::factory()
            ->for($baby)
            ->create([
                'baby_action_type_id' => $actionType->id,
                'started_at' => now()->subHours(1),
                'finished_at' => null,
            ]);

        $result = $this->scheduler->scheduleFor($action);

        $this->assertFalse($result);
        $action->refresh();
        $this->assertNull($action->notification_scheduled_at);
    }

    public function test_schedule_for_with_disabled_setting_returns_false(): void
    {
        $baby = Baby::factory()->create();
        $actionType = BabyActionType::factory()->create();
        $this->settingFor($actionType, ['enabled' => false]);

        $action = BabyAction::factory()
            ->for($baby)
            ->create([
                'baby_action_type_id' => $actionType->id,
                'started_at' => now()->subHours(1),
                'finished_at' => null,
            ]);

        $result = $this->scheduler->scheduleFor($action);

        $this->assertFalse($result);
        $action->refresh();
        $this->assertNull($action->notification_scheduled_at);
    }

    public function test_schedule_for_with_two_enabled_rules_schedules_two_keys(): void
    {
        $baby = Baby::factory()->create();
        $actionType = BabyActionType::factory()->create();
        $ruleA = $this->settingFor($actionType, ['notify_after_minutes' => 180]);
        $ruleB = $this->settingFor($actionType, ['notify_after_minutes' => 240]);

        $action = BabyAction::factory()
            ->for($baby)
            ->create([
                'baby_action_type_id' => $actionType->id,
                'started_at' => now()->subHours(1),
                'finished_at' => null,
            ]);

        $result = $this->scheduler->scheduleFor($action);

        $this->assertTrue($result);
        $action->refresh();
        $this->assertCount(2, $action->scheduled_notification_keys);
        $this->assertContains("action-{$action->id}-setting-{$ruleA->id}", $action->scheduled_notification_keys);
        $this->assertContains("action-{$action->id}-setting-{$ruleB->id}", $action->scheduled_notification_keys);
    }

    public function test_schedule_for_skips_disabled_rule_among_enabled(): void
    {
        $baby = Baby::factory()->create();
        $actionType = BabyActionType::factory()->create();
        $enabledRule = $this->settingFor($actionType, ['notify_after_minutes' => 180]);
        $disabledRule = $this->settingFor($actionType, ['notify_after_minutes' => 240, 'enabled' => false]);

        $action = BabyAction::factory()
            ->for($baby)
            ->create([
                'baby_action_type_id' => $actionType->id,
                'started_at' => now()->subHours(1),
                'finished_at' => null,
            ]);

        $this->scheduler->scheduleFor($action);

        $action->refresh();
        $this->assertCount(1, $action->scheduled_notification_keys);
        $this->assertContains("action-{$action->id}-setting-{$enabledRule->id}", $action->scheduled_notification_keys);
        $this->assertNotContains("action-{$action->id}-setting-{$disabledRule->id}", $action->scheduled_notification_keys);
    }

    public function test_cancel_for_clears_all_stored_keys(): void
    {
        $baby = Baby::factory()->create();
        $actionType = BabyActionType::factory()->create();
        $this->settingFor($actionType, ['notify_after_minutes' => 180]);
        $this->settingFor($actionType, ['notify_after_minutes' => 240]);

        $action = BabyAction::factory()
            ->for($baby)
            ->create([
                'baby_action_type_id' => $actionType->id,
                'started_at' => now()->subHours(1),
                'finished_at' => null,
            ]);

        $action->refresh();
        $this->assertCount(2, $action->scheduled_notification_keys);

        $this->scheduler->cancelFor($action);

        $action->refresh();
        $this->assertNull($action->notification_scheduled_at);
        $this->assertNull($action->scheduled_notification_keys);
    }

    public function test_cancel_for_sets_notification_scheduled_at_to_null(): void
    {
        $baby = Baby::factory()->create();
        $actionType = BabyActionType::factory()->create();
        $action = BabyAction::factory()
            ->for($baby)
            ->create([
                'baby_action_type_id' => $actionType->id,
                'notification_scheduled_at' => now(),
            ]);

        $this->scheduler->cancelFor($action);

        $action->refresh();
        $this->assertNull($action->notification_scheduled_at);
    }

    public function test_cancel_all_for_type_cancels_all_pending(): void
    {
        $baby = Baby::factory()->create();
        $actionType = BabyActionType::factory()->create();

        $action1 = BabyAction::factory()
            ->for($baby)
            ->create([
                'baby_action_type_id' => $actionType->id,
                'notification_scheduled_at' => now(),
            ]);
        $action2 = BabyAction::factory()
            ->for($baby)
            ->create([
                'baby_action_type_id' => $actionType->id,
                'notification_scheduled_at' => now(),
            ]);

        $count = $this->scheduler->cancelAllForType($actionType);

        $this->assertEquals(2, $count);
        $action1->refresh();
        $action2->refresh();
        $this->assertNull($action1->notification_scheduled_at);
        $this->assertNull($action2->notification_scheduled_at);
    }

    public function test_baby_action_observer_created_schedules_notification(): void
    {
        $baby = Baby::factory()->create();
        $actionType = BabyActionType::factory()->create();
        $this->settingFor($actionType);

        $action = BabyAction::factory()
            ->for($baby)
            ->create([
                'baby_action_type_id' => $actionType->id,
                'started_at' => now()->subHours(1),
            ]);

        $action->refresh();
        $this->assertNotNull($action->notification_scheduled_at);
    }

    public function test_baby_action_observer_updated_with_started_at_changed_reschedules(): void
    {
        $baby = Baby::factory()->create();
        $actionType = BabyActionType::factory()->create();
        $this->settingFor($actionType);

        $action = BabyAction::factory()
            ->for($baby)
            ->create([
                'baby_action_type_id' => $actionType->id,
                'started_at' => now()->subHours(1),
            ]);

        $this->assertNotNull($action->notification_scheduled_at);
        $originalScheduledAt = $action->notification_scheduled_at;

        $this->travel(1)->minutes();
        $action->update(['started_at' => now()->subMinutes(30)]);

        $action->refresh();
        $this->assertNotNull($action->notification_scheduled_at);
        $this->assertTrue(
            $action->notification_scheduled_at->greaterThan($originalScheduledAt),
            'Rescheduling should refresh notification_scheduled_at to a later timestamp.'
        );
    }

    public function test_baby_action_observer_updated_with_unrelated_field_does_not_reschedule(): void
    {
        $baby = Baby::factory()->create();
        $otherBaby = Baby::factory()->create();
        $actionType = BabyActionType::factory()->create();
        $this->settingFor($actionType);

        $action = BabyAction::factory()
            ->for($baby)
            ->create([
                'baby_action_type_id' => $actionType->id,
                'started_at' => now()->subHours(1),
            ]);

        $originalScheduledAt = $action->notification_scheduled_at;

        // Update an unrelated field - should not trigger reschedule
        $action->update(['baby_id' => $otherBaby->id]);

        $action->refresh();
        $this->assertEquals($originalScheduledAt, $action->notification_scheduled_at);
    }

    public function test_action_type_change_swaps_scheduled_keys(): void
    {
        $baby = Baby::factory()->create();
        $typeA = BabyActionType::factory()->create();
        $typeB = BabyActionType::factory()->create();
        $ruleA = $this->settingFor($typeA);
        $ruleB = $this->settingFor($typeB);

        $action = BabyAction::factory()
            ->for($baby)
            ->create([
                'baby_action_type_id' => $typeA->id,
                'started_at' => now()->subHour(),
            ]);

        $action->refresh();
        $this->assertEquals(["action-{$action->id}-setting-{$ruleA->id}"], $action->scheduled_notification_keys);

        $action->update(['baby_action_type_id' => $typeB->id]);

        $action->refresh();
        $this->assertEquals(["action-{$action->id}-setting-{$ruleB->id}"], $action->scheduled_notification_keys);
    }

    public function test_reschedule_all_for_type_reschedules_future_and_drops_past(): void
    {
        $baby = Baby::factory()->create();
        $actionType = BabyActionType::factory()->create();
        $this->settingFor($actionType);

        // Future: started 1h ago → fires in ~2h → stays scheduled.
        $future = BabyAction::factory()
            ->for($baby)
            ->create([
                'baby_action_type_id' => $actionType->id,
                'started_at' => now()->subHour(),
            ]);

        // Past: started 4h ago → fires 1h ago → cannot be rescheduled.
        $past = BabyAction::factory()
            ->for($baby)
            ->create([
                'baby_action_type_id' => $actionType->id,
                'started_at' => now()->subHours(4),
            ]);

        $count = $this->scheduler->rescheduleAllForType($actionType);

        $this->assertEquals(1, $count);
        $this->assertNotNull($future->refresh()->notification_scheduled_at);
        $this->assertNull($past->refresh()->notification_scheduled_at);
    }
}
