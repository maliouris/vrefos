<?php

namespace Tests\Feature;

use App\Enums\IntervalUnit;
use App\Models\Baby;
use App\Models\BabyAction;
use App\Models\BabyActionType;
use App\Models\Notification;
use App\Models\Plan;
use App\Models\PlanVersion;
use App\Services\PlanScheduler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class PlanSchedulerTest extends TestCase
{
    use RefreshDatabase;

    private PlanScheduler $scheduler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scheduler = app(PlanScheduler::class);
    }

    /**
     * Run scheduleFor through a partial mock that captures the payloads
     * handed to the native plugin, so tests can assert the exact `at`
     * timestamp / repeat config without a native runtime.
     *
     * @param  array<int, array<string, mixed>>  $captured
     */
    private function captureScheduled(Plan $plan, array &$captured): bool
    {
        $scheduler = Mockery::mock(PlanScheduler::class)
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $scheduler->shouldReceive('dispatchSchedule')
            ->andReturnUsing(function (array $payload) use (&$captured): void {
                $captured[] = $payload;
            });

        return $scheduler->scheduleFor($plan);
    }

    private function hoursPlan(BabyActionType $type, array $planOverrides = [], array $versionOverrides = []): Plan
    {
        $plan = Plan::factory()->create(array_merge([
            'baby_action_type_id' => $type->id,
            'all_children' => true,
        ], $planOverrides));

        PlanVersion::factory()->for($plan)->create(array_merge([
            'interval_minutes' => 180,
            'interval_unit' => IntervalUnit::Hours,
        ], $versionOverrides));

        return $plan;
    }

    public function test_no_attached_notifications_schedules_nothing(): void
    {
        $baby = Baby::factory()->create();
        $type = BabyActionType::factory()->create();
        $plan = $this->hoursPlan($type);

        BabyAction::factory()->for($baby)->create(['baby_action_type_id' => $type->id, 'started_at' => now()->subHour()]);

        $result = $this->scheduler->scheduleFor($plan);

        $this->assertFalse($result);
        $this->assertNull($plan->refresh()->notification_scheduled_at);
    }

    public function test_hours_plan_reanchors_from_last_action(): void
    {
        $baby = Baby::factory()->create();
        $type = BabyActionType::factory()->create();
        $plan = $this->hoursPlan($type);
        $plan->startNotifications()->attach(Notification::factory()->create(), ['trigger' => 'start']);

        $action = BabyAction::factory()->for($baby)->create([
            'baby_action_type_id' => $type->id,
            'started_at' => now()->subMinutes(10),
        ]);

        $captured = [];
        $result = $this->captureScheduled($plan, $captured);

        $this->assertTrue($result);
        $this->assertCount(1, $captured);
        $this->assertSame($action->started_at->copy()->addMinutes(180)->timestamp, $captured[0]['at']);
        $this->assertSame("plan-{$plan->id}-baby-{$baby->id}-notif-{$plan->startNotifications->first()->id}-start", $captured[0]['id']);
    }

    public function test_hours_plan_with_no_prior_action_schedules_from_now(): void
    {
        $baby = Baby::factory()->create();
        $type = BabyActionType::factory()->create();
        $plan = $this->hoursPlan($type);
        $plan->startNotifications()->attach(Notification::factory()->create(), ['trigger' => 'start']);

        $captured = [];
        $this->captureScheduled($plan, $captured);

        $this->assertCount(1, $captured);
        $this->assertEqualsWithDelta(now()->addMinutes(180)->timestamp, $captured[0]['at'], 5);
    }

    public function test_daily_plan_produces_repeat_daily_payload(): void
    {
        $baby = Baby::factory()->create();
        $type = BabyActionType::factory()->create();
        $plan = Plan::factory()->create(['baby_action_type_id' => $type->id, 'all_children' => true]);
        PlanVersion::factory()->daily(['08:00'])->for($plan)->create();
        $plan->startNotifications()->attach(Notification::factory()->create(), ['trigger' => 'start']);

        $captured = [];
        $this->captureScheduled($plan, $captured);

        $this->assertCount(1, $captured);
        $this->assertSame('daily', $captured[0]['repeat']);
    }

    public function test_weekly_plan_with_weekdays_produces_repeat_days_payload(): void
    {
        $baby = Baby::factory()->create();
        $type = BabyActionType::factory()->create();
        $plan = Plan::factory()->create(['baby_action_type_id' => $type->id, 'all_children' => true]);
        PlanVersion::factory()->weekly([1, 3, 5], ['08:00'])->for($plan)->create();
        $plan->startNotifications()->attach(Notification::factory()->create(), ['trigger' => 'start']);

        $captured = [];
        $this->captureScheduled($plan, $captured);

        $this->assertCount(1, $captured);
        $this->assertSame([1, 3, 5], $captured[0]['repeatDays']);
    }

    public function test_end_trigger_skipped_when_last_action_unfinished(): void
    {
        $baby = Baby::factory()->create();
        $type = BabyActionType::factory()->create();
        $plan = $this->hoursPlan($type, versionOverrides: ['duration_minutes' => 60]);
        $plan->endNotifications()->attach(Notification::factory()->create(), ['trigger' => 'end']);

        BabyAction::factory()->for($baby)->create([
            'baby_action_type_id' => $type->id,
            'started_at' => now()->subMinutes(10),
            'finished_at' => null,
        ]);

        $captured = [];
        $this->captureScheduled($plan, $captured);

        $this->assertCount(0, $captured);
    }

    public function test_end_trigger_fires_from_finished_at_plus_duration(): void
    {
        $baby = Baby::factory()->create();
        $type = BabyActionType::factory()->create();
        $plan = $this->hoursPlan($type, versionOverrides: ['duration_minutes' => 60]);
        $plan->endNotifications()->attach(Notification::factory()->create(), ['trigger' => 'end']);

        $action = BabyAction::factory()->for($baby)->create([
            'baby_action_type_id' => $type->id,
            'started_at' => now()->subHour(),
            'finished_at' => now()->subMinutes(30),
        ]);

        $captured = [];
        $this->captureScheduled($plan, $captured);

        $this->assertCount(1, $captured);
        $this->assertSame($action->finished_at->copy()->addMinutes(60)->timestamp, $captured[0]['at']);
    }

    public function test_child_targeting_skips_untargeted_baby(): void
    {
        $targetedBaby = Baby::factory()->create();
        $otherBaby = Baby::factory()->create();
        $type = BabyActionType::factory()->create();

        $plan = Plan::factory()->create(['baby_action_type_id' => $type->id, 'all_children' => false]);
        PlanVersion::factory()->for($plan)->create(['interval_minutes' => 180, 'interval_unit' => IntervalUnit::Hours]);
        $plan->babies()->sync([$targetedBaby->id]);
        $plan->startNotifications()->attach(Notification::factory()->create(), ['trigger' => 'start']);

        $captured = [];
        $this->captureScheduled($plan, $captured);

        $this->assertCount(1, $captured);
        $this->assertStringContainsString("baby-{$targetedBaby->id}-", $captured[0]['id']);
        $this->assertStringNotContainsString("baby-{$otherBaby->id}-", $captured[0]['id']);
    }

    public function test_cancel_for_clears_bookkeeping(): void
    {
        $baby = Baby::factory()->create();
        $type = BabyActionType::factory()->create();
        $plan = $this->hoursPlan($type);
        $plan->startNotifications()->attach(Notification::factory()->create(), ['trigger' => 'start']);

        $this->scheduler->scheduleFor($plan);
        $this->assertNotNull($plan->refresh()->notification_scheduled_at);

        $this->scheduler->cancelFor($plan);

        $this->assertNull($plan->refresh()->notification_scheduled_at);
        $this->assertNull($plan->refresh()->scheduled_notification_keys);
    }

    public function test_disabled_plan_schedules_nothing(): void
    {
        $baby = Baby::factory()->create();
        $type = BabyActionType::factory()->create();
        $plan = $this->hoursPlan($type, ['enabled' => false]);
        $plan->startNotifications()->attach(Notification::factory()->create(), ['trigger' => 'start']);

        $result = $this->scheduler->scheduleFor($plan);

        $this->assertFalse($result);
    }

    public function test_ends_on_in_the_past_skips_hours_plan(): void
    {
        $baby = Baby::factory()->create();
        $type = BabyActionType::factory()->create();
        $plan = $this->hoursPlan($type, ['ends_on' => now()->subDay()->toDateString()]);
        $plan->startNotifications()->attach(Notification::factory()->create(), ['trigger' => 'start']);

        $captured = [];
        $this->captureScheduled($plan, $captured);

        $this->assertCount(0, $captured);
    }

    public function test_bounded_daily_plan_gets_repeat_count(): void
    {
        $baby = Baby::factory()->create();
        $type = BabyActionType::factory()->create();
        $plan = Plan::factory()->create([
            'baby_action_type_id' => $type->id,
            'all_children' => true,
            'ends_on' => now()->addDays(2)->toDateString(),
        ]);
        PlanVersion::factory()->daily(['08:00'])->for($plan)->create();
        $plan->startNotifications()->attach(Notification::factory()->create(), ['trigger' => 'start']);

        $captured = [];
        $this->captureScheduled($plan, $captured);

        $this->assertCount(1, $captured);
        $this->assertArrayHasKey('repeatCount', $captured[0]);
        $this->assertGreaterThanOrEqual(1, $captured[0]['repeatCount']);
    }

    public function test_reanchor_for_action_only_touches_hours_plans_of_matching_type_and_baby(): void
    {
        $baby = Baby::factory()->create();
        $type = BabyActionType::factory()->create();
        $otherType = BabyActionType::factory()->create();

        $hoursPlan = $this->hoursPlan($type);
        $hoursPlan->startNotifications()->attach(Notification::factory()->create(), ['trigger' => 'start']);

        $dailyPlan = Plan::factory()->create(['baby_action_type_id' => $type->id, 'all_children' => true]);
        PlanVersion::factory()->daily()->for($dailyPlan)->create();
        $dailyPlan->startNotifications()->attach(Notification::factory()->create(), ['trigger' => 'start']);

        $otherTypePlan = $this->hoursPlan($otherType);
        $otherTypePlan->startNotifications()->attach(Notification::factory()->create(), ['trigger' => 'start']);

        $action = BabyAction::factory()->for($baby)->create(['baby_action_type_id' => $type->id, 'started_at' => now()]);

        $this->scheduler->reanchorForAction($action);

        $this->assertNotNull($hoursPlan->refresh()->notification_scheduled_at);
        $this->assertNull($dailyPlan->refresh()->notification_scheduled_at);
        $this->assertNull($otherTypePlan->refresh()->notification_scheduled_at);
    }
}
