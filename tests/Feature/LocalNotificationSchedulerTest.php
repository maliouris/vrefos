<?php

namespace Tests\Feature;

use App\Enums\NotifyFrom;
use App\Models\Baby;
use App\Models\BabyAction;
use App\Models\BabyActionType;
use App\Models\NotificationSetting;
use App\Services\LocalNotificationScheduler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
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
            'title' => 'Time to check on your baby!',
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

    public function test_schedule_for_with_fire_at_in_past_fires_immediately(): void
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

        $this->assertTrue($result);
        $action->refresh();
        $this->assertNotNull($action->notification_scheduled_at);
        $this->assertCount(1, $action->scheduled_notification_keys);
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

    public function test_rule_targeting_specific_baby_only_schedules_for_that_baby(): void
    {
        $targetBaby = Baby::factory()->create();
        $otherBaby = Baby::factory()->create();
        $actionType = BabyActionType::factory()->create();

        $rule = $this->settingFor($actionType, ['all_children' => false]);
        $rule->babies()->attach($targetBaby->id);

        $targetAction = BabyAction::factory()
            ->for($targetBaby)
            ->create([
                'baby_action_type_id' => $actionType->id,
                'started_at' => now()->subHour(),
            ]);

        $otherAction = BabyAction::factory()
            ->for($otherBaby)
            ->create([
                'baby_action_type_id' => $actionType->id,
                'started_at' => now()->subHour(),
            ]);

        $this->assertNotNull($targetAction->fresh()->notification_scheduled_at);
        $this->assertNull($otherAction->fresh()->notification_scheduled_at);
    }

    public function test_all_children_rule_schedules_for_every_baby(): void
    {
        $babyA = Baby::factory()->create();
        $babyB = Baby::factory()->create();
        $actionType = BabyActionType::factory()->create();

        $this->settingFor($actionType, ['all_children' => true]);

        $actionA = BabyAction::factory()
            ->for($babyA)
            ->create([
                'baby_action_type_id' => $actionType->id,
                'started_at' => now()->subHour(),
            ]);

        $actionB = BabyAction::factory()
            ->for($babyB)
            ->create([
                'baby_action_type_id' => $actionType->id,
                'started_at' => now()->subHour(),
            ]);

        $this->assertNotNull($actionA->fresh()->notification_scheduled_at);
        $this->assertNotNull($actionB->fresh()->notification_scheduled_at);
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

    public function test_reschedule_all_for_type_reschedules_future_and_past(): void
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

        // Past: started 4h ago → fires 1h ago → now fires immediately (2.B).
        $past = BabyAction::factory()
            ->for($baby)
            ->create([
                'baby_action_type_id' => $actionType->id,
                'started_at' => now()->subHours(4),
            ]);

        $count = $this->scheduler->rescheduleAllForType($actionType);

        $this->assertEquals(2, $count);
        $this->assertNotNull($future->refresh()->notification_scheduled_at);
        $this->assertNotNull($past->refresh()->notification_scheduled_at);
    }

    public function test_future_rule_schedules_at_reference_plus_minutes(): void
    {
        $baby = Baby::factory()->create();
        $actionType = BabyActionType::factory()->create();
        $this->settingFor($actionType, ['notify_after_minutes' => 180]);

        $action = BabyAction::factory()
            ->for($baby)
            ->create([
                'baby_action_type_id' => $actionType->id,
                'started_at' => now()->subMinutes(10),
                'finished_at' => null,
            ]);

        $captured = [];
        $result = $this->captureScheduled($action, $captured);

        $this->assertTrue($result);
        $this->assertCount(1, $captured);
        $this->assertSame(
            $action->started_at->copy()->addMinutes(180)->timestamp,
            $captured[0]['at'],
        );
    }

    public function test_payload_title_and_body_come_from_the_rule_with_placeholders(): void
    {
        $baby = Baby::factory()->create(['name' => 'Lily']);
        $actionType = BabyActionType::factory()->create(['name' => 'Eat']);
        $this->settingFor($actionType, [
            'notify_after_minutes' => 120,
            'title' => '#{baby}, time to #{action}!',
            'description' => 'Due in #{minutes} min',
        ]);

        $action = BabyAction::factory()
            ->for($baby)
            ->create([
                'baby_action_type_id' => $actionType->id,
                'started_at' => now()->subMinutes(10),
                'finished_at' => null,
            ]);

        $captured = [];
        $this->captureScheduled($action, $captured);

        $this->assertCount(1, $captured);
        $this->assertSame('Lily, time to eat!', $captured[0]['title']);
        $this->assertSame('Due in 120 min', $captured[0]['body']);
    }

    public function test_blank_description_yields_empty_body(): void
    {
        $baby = Baby::factory()->create();
        $actionType = BabyActionType::factory()->create();
        $this->settingFor($actionType, ['title' => 'Heads up!', 'description' => null]);

        $action = BabyAction::factory()
            ->for($baby)
            ->create([
                'baby_action_type_id' => $actionType->id,
                'started_at' => now()->subMinutes(10),
                'finished_at' => null,
            ]);

        $captured = [];
        $this->captureScheduled($action, $captured);

        $this->assertCount(1, $captured);
        $this->assertSame('Heads up!', $captured[0]['title']);
        $this->assertSame('', $captured[0]['body']);
    }

    public function test_past_due_rule_schedules_immediately(): void
    {
        $this->freezeTime();

        $baby = Baby::factory()->create();
        $actionType = BabyActionType::factory()->create();
        $this->settingFor($actionType, ['notify_after_minutes' => 60]);

        $action = BabyAction::factory()
            ->for($baby)
            ->create([
                'baby_action_type_id' => $actionType->id,
                'started_at' => now()->subHours(2),
                'finished_at' => null,
            ]);

        $captured = [];
        $result = $this->captureScheduled($action, $captured);

        $this->assertTrue($result);
        $this->assertCount(1, $captured);
        $this->assertSame(now()->addSeconds(1)->timestamp, $captured[0]['at']);
    }

    public function test_null_reference_time_still_skips(): void
    {
        $baby = Baby::factory()->create();
        $actionType = BabyActionType::factory()->create();
        $this->settingFor($actionType, ['notify_from' => NotifyFrom::FinishedAt]);

        $action = BabyAction::factory()
            ->for($baby)
            ->create([
                'baby_action_type_id' => $actionType->id,
                'started_at' => now()->subHours(2),
                'finished_at' => null,
            ]);

        $captured = [];
        $result = $this->captureScheduled($action, $captured);

        $this->assertFalse($result);
        $this->assertCount(0, $captured);
        $this->assertNull($action->refresh()->notification_scheduled_at);
    }

    /**
     * Run scheduleFor through a partial mock that captures the payloads handed
     * to the native plugin (the dispatchSchedule seam), so tests can assert the
     * exact `at` timestamp without a native runtime.
     *
     * @param  array<int, array<string, mixed>>  $captured
     */
    private function captureScheduled(BabyAction $action, array &$captured): bool
    {
        $scheduler = Mockery::mock(LocalNotificationScheduler::class)
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $scheduler->shouldReceive('dispatchSchedule')
            ->andReturnUsing(function (array $payload) use (&$captured): void {
                $captured[] = $payload;
            });

        return $scheduler->scheduleFor($action);
    }
}
