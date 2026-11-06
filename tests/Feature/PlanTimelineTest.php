<?php

namespace Tests\Feature;

use App\Enums\IntervalUnit;
use App\Models\Baby;
use App\Models\BabyAction;
use App\Models\BabyActionType;
use App\Models\Plan;
use App\Models\PlanVersion;
use App\Services\PlanTimeline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PlanTimelineTest extends TestCase
{
    use RefreshDatabase;

    private PlanTimeline $timeline;

    protected function setUp(): void
    {
        parent::setUp();
        $this->timeline = app(PlanTimeline::class);
    }

    public function test_daily_plan_projects_one_slot_per_day(): void
    {
        $baby = Baby::factory()->create();
        $type = BabyActionType::factory()->create();
        $plan = Plan::factory()->create(['baby_action_type_id' => $type->id, 'all_children' => true]);
        PlanVersion::factory()->daily(['08:00'])->for($plan)->create();

        $from = now()->startOfDay();
        $until = now()->addDays(2)->endOfDay();

        $slots = $this->timeline->project(
            Plan::with(['versions', 'babies'])->get(),
            collect([$baby]),
            collect(),
            $from,
            $until,
            'UTC',
        );

        $this->assertCount(3, $slots);
    }

    public function test_logged_action_matches_a_daily_slot(): void
    {
        $baby = Baby::factory()->create();
        $type = BabyActionType::factory()->create();
        $plan = Plan::factory()->create(['baby_action_type_id' => $type->id, 'all_children' => true]);
        PlanVersion::factory()->daily(['08:00'])->for($plan)->create();

        $today = now()->startOfDay();
        $slotAt = Carbon::parse($today->toDateString().' 08:00', 'UTC');

        $action = BabyAction::factory()->for($baby)->create([
            'baby_action_type_id' => $type->id,
            'started_at' => $slotAt->copy()->addMinutes(10),
        ]);

        $slots = $this->timeline->project(
            Plan::with(['versions', 'babies'])->get(),
            collect([$baby]),
            collect([$action]),
            $today,
            $today->copy()->endOfDay(),
            'UTC',
        );

        $this->assertCount(1, $slots);
        $this->assertSame('done', $slots[0]['status']);
        $this->assertSame($action->id, $slots[0]['matched_action_id']);
    }

    public function test_past_unmatched_slot_is_missed(): void
    {
        $baby = Baby::factory()->create();
        $type = BabyActionType::factory()->create();
        $plan = Plan::factory()->create(['baby_action_type_id' => $type->id, 'all_children' => true]);
        PlanVersion::factory()->daily(['08:00'])->for($plan)->create([
            'effective_from' => now()->subWeek()->toDateString(),
        ]);

        $yesterday = now()->subDay()->startOfDay();

        $slots = $this->timeline->project(
            Plan::with(['versions', 'babies'])->get(),
            collect([$baby]),
            collect(),
            $yesterday,
            $yesterday->copy()->endOfDay(),
            'UTC',
        );

        $this->assertCount(1, $slots);
        $this->assertSame('missed', $slots[0]['status']);
    }

    public function test_hours_chain_resets_from_logged_action(): void
    {
        $baby = Baby::factory()->create();
        $type = BabyActionType::factory()->create();
        $plan = Plan::factory()->create(['baby_action_type_id' => $type->id, 'all_children' => true]);
        PlanVersion::factory()->for($plan)->create(['interval_minutes' => 180, 'interval_unit' => IntervalUnit::Hours]);

        $action = BabyAction::factory()->for($baby)->create([
            'baby_action_type_id' => $type->id,
            'started_at' => now()->subHour(),
        ]);

        $from = now()->subHours(2);
        $until = now()->addHours(6);

        $slots = $this->timeline->project(
            Plan::with(['versions', 'babies'])->get(),
            collect([$baby]),
            collect([$action]),
            $from,
            $until,
            'UTC',
        );

        $this->assertNotEmpty($slots);
        $first = collect($slots)->sortBy('at')->first();
        $this->assertEqualsWithDelta(
            $action->started_at->copy()->addMinutes(180)->timestamp,
            $first['at']->timestamp,
            1,
        );
    }

    public function test_range_clipped_to_plan_bounds(): void
    {
        $baby = Baby::factory()->create();
        $type = BabyActionType::factory()->create();
        $plan = Plan::factory()->create([
            'baby_action_type_id' => $type->id,
            'all_children' => true,
            'starts_on' => now()->addDays(5)->toDateString(),
        ]);
        PlanVersion::factory()->daily(['08:00'])->for($plan)->create();

        $slots = $this->timeline->project(
            Plan::with(['versions', 'babies'])->get(),
            collect([$baby]),
            collect(),
            now()->startOfDay(),
            now()->addDays(2)->endOfDay(),
            'UTC',
        );

        $this->assertCount(0, $slots);
    }

    public function test_versioned_recurrence_uses_the_rule_active_on_each_day(): void
    {
        $baby = Baby::factory()->create();
        $type = BabyActionType::factory()->create();
        $plan = Plan::factory()->create(['baby_action_type_id' => $type->id, 'all_children' => true]);

        PlanVersion::factory()->daily(['08:00'])->for($plan)->create([
            'effective_from' => now()->subDays(5)->toDateString(),
        ]);
        PlanVersion::factory()->daily(['08:00', '18:00'])->for($plan)->create([
            'effective_from' => now()->toDateString(),
        ]);

        $slotsYesterday = $this->timeline->project(
            Plan::with(['versions', 'babies'])->get(),
            collect([$baby]),
            collect(),
            now()->subDay()->startOfDay(),
            now()->subDay()->endOfDay(),
            'UTC',
        );

        $slotsToday = $this->timeline->project(
            Plan::with(['versions', 'babies'])->get(),
            collect([$baby]),
            collect(),
            now()->startOfDay(),
            now()->endOfDay(),
            'UTC',
        );

        $this->assertCount(1, $slotsYesterday);
        $this->assertCount(2, $slotsToday);
    }
}
