<?php

namespace Tests\Feature;

use App\Models\Baby;
use App\Models\BabyActionType;
use App\Models\Plan;
use App\Models\PlanVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BabyObserverTest extends TestCase
{
    use RefreshDatabase;

    private function planFor(BabyActionType $type, array $overrides = []): Plan
    {
        $plan = Plan::factory()->create(array_merge([
            'baby_action_type_id' => $type->id,
            'all_children' => true,
        ], $overrides));

        PlanVersion::factory()->for($plan)->create();

        return $plan;
    }

    public function test_new_baby_is_attached_to_all_children_plans_only(): void
    {
        $type = BabyActionType::factory()->create();
        $allChildrenPlan = $this->planFor($type, ['all_children' => true]);
        $specificPlan = $this->planFor($type, ['all_children' => false]);

        $baby = Baby::factory()->create();

        $attachedPlanIds = $baby->plans()->pluck('plans.id')->all();
        $this->assertContains($allChildrenPlan->id, $attachedPlanIds);
        $this->assertNotContains($specificPlan->id, $attachedPlanIds);
        $this->assertEmpty($specificPlan->babies()->pluck('babies.id')->all());
    }

    public function test_deleting_a_baby_removes_its_pivot_rows(): void
    {
        $type = BabyActionType::factory()->create();
        $plan = $this->planFor($type, ['all_children' => true]);

        $baby = Baby::factory()->create();
        $this->assertDatabaseHas('baby_plan', [
            'baby_id' => $baby->id,
            'plan_id' => $plan->id,
        ]);

        $baby->delete();

        $this->assertDatabaseMissing('baby_plan', [
            'baby_id' => $baby->id,
        ]);
    }
}
