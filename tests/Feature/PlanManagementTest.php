<?php

namespace Tests\Feature;

use App\Livewire\Pages\Planning\Index;
use App\Models\Baby;
use App\Models\BabyActionType;
use App\Models\Notification;
use App\Models\Plan;
use App\Models\PlanVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PlanManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_an_hours_plan(): void
    {
        $type = BabyActionType::factory()->create();
        $baby = Baby::factory()->create();
        $notification = Notification::factory()->create();

        Livewire::test(Index::class)
            ->set('babyActionTypeId', $type->id)
            ->set('intervalUnit', 'hours')
            ->set('intervalValue', 3)
            ->set('startNotificationIds', [$notification->id])
            ->call('savePlan')
            ->assertHasNoErrors();

        $plan = Plan::firstOrFail();
        $this->assertSame($type->id, $plan->baby_action_type_id);
        $this->assertTrue($plan->all_children);

        $version = $plan->currentVersion();
        $this->assertSame(180, $version->interval_minutes);
        $this->assertSame([$baby->id], $plan->babies()->pluck('babies.id')->all());
        $this->assertSame([$notification->id], $plan->startNotifications->pluck('id')->all());
    }

    public function test_interval_value_round_trips_through_minutes(): void
    {
        $type = BabyActionType::factory()->create();

        Livewire::test(Index::class)
            ->set('babyActionTypeId', $type->id)
            ->set('intervalUnit', 'days')
            ->set('intervalValue', 3)
            ->set('times', ['08:00'])
            ->call('savePlan')
            ->assertHasNoErrors();

        $plan = Plan::firstOrFail();
        $this->assertSame(4320, $plan->currentVersion()->interval_minutes);

        Livewire::test(Index::class)
            ->call('openEdit', $plan->id)
            ->assertSet('intervalUnit', 'days')
            ->assertSet('intervalValue', 3.0);
    }

    public function test_ends_on_must_be_after_or_equal_to_starts_on(): void
    {
        $type = BabyActionType::factory()->create();

        Livewire::test(Index::class)
            ->set('babyActionTypeId', $type->id)
            ->set('startsOn', now()->addDays(5)->toDateString())
            ->set('endsOn', now()->toDateString())
            ->call('savePlan')
            ->assertHasErrors(['endsOn']);
    }

    public function test_editing_recurrence_creates_a_new_version_effective_today_without_touching_history(): void
    {
        $type = BabyActionType::factory()->create();
        $plan = Plan::factory()->create(['baby_action_type_id' => $type->id]);
        $oldVersion = PlanVersion::factory()->for($plan)->create([
            'effective_from' => now()->subWeek()->toDateString(),
            'interval_minutes' => 180,
        ]);

        Livewire::test(Index::class)
            ->call('openEdit', $plan->id)
            ->set('intervalValue', 4)
            ->call('savePlan')
            ->assertHasNoErrors();

        $this->assertSame(180, $oldVersion->fresh()->interval_minutes);
        $this->assertCount(2, $plan->versions()->get());
        $this->assertSame(240, $plan->currentVersion()->interval_minutes);
    }

    public function test_same_day_recurrence_edit_upserts_the_same_version_row(): void
    {
        $type = BabyActionType::factory()->create();
        $plan = Plan::factory()->create(['baby_action_type_id' => $type->id]);
        PlanVersion::factory()->for($plan)->create(['effective_from' => now()->toDateString()]);

        Livewire::test(Index::class)
            ->call('openEdit', $plan->id)
            ->set('intervalValue', 5)
            ->call('savePlan')
            ->assertHasNoErrors();

        $this->assertCount(1, $plan->versions()->get());
        $this->assertSame(300, $plan->currentVersion()->interval_minutes);
    }

    public function test_can_attach_start_and_end_notifications(): void
    {
        $type = BabyActionType::factory()->create();
        $start = Notification::factory()->create();
        $end = Notification::factory()->create();

        Livewire::test(Index::class)
            ->set('babyActionTypeId', $type->id)
            ->set('durationMinutes', '30')
            ->set('startNotificationIds', [$start->id])
            ->set('endNotificationIds', [$end->id])
            ->call('savePlan')
            ->assertHasNoErrors();

        $plan = Plan::firstOrFail();
        $this->assertSame([$start->id], $plan->startNotifications->pluck('id')->all());
        $this->assertSame([$end->id], $plan->endNotifications->pluck('id')->all());
    }

    public function test_inline_new_message_creates_and_attaches(): void
    {
        $type = BabyActionType::factory()->create();

        Livewire::test(Index::class)
            ->set('babyActionTypeId', $type->id)
            ->set('newMessageTitle', 'Quick message')
            ->call('createAndAttachMessage', 'start')
            ->call('savePlan')
            ->assertHasNoErrors();

        $notification = Notification::where('title', 'Quick message')->firstOrFail();
        $this->assertSame([$notification->id], Plan::firstOrFail()->startNotifications->pluck('id')->all());
    }

    public function test_all_children_targeting_attaches_every_baby(): void
    {
        $type = BabyActionType::factory()->create();
        $babyA = Baby::factory()->create();
        $babyB = Baby::factory()->create();

        Livewire::test(Index::class)
            ->set('babyActionTypeId', $type->id)
            ->call('savePlan')
            ->assertHasNoErrors();

        $plan = Plan::firstOrFail();
        $this->assertEqualsCanonicalizing([$babyA->id, $babyB->id], $plan->babies()->pluck('babies.id')->all());
    }

    public function test_specific_child_targeting_requires_at_least_one_baby(): void
    {
        $type = BabyActionType::factory()->create();
        Baby::factory()->create();

        Livewire::test(Index::class)
            ->set('babyActionTypeId', $type->id)
            ->set('allChildren', false)
            ->set('targetBabyIds', [])
            ->call('savePlan')
            ->assertHasErrors(['targetBabyIds']);
    }

    public function test_can_delete_a_plan(): void
    {
        $type = BabyActionType::factory()->create();
        $plan = Plan::factory()->create(['baby_action_type_id' => $type->id]);
        PlanVersion::factory()->for($plan)->create();

        Livewire::test(Index::class)
            ->call('promptDeletePlan', $plan->id)
            ->call('deletePlan');

        $this->assertDatabaseMissing('plans', ['id' => $plan->id]);
    }
}
