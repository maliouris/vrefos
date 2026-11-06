<?php

namespace Tests\Feature;

use App\Livewire\Pages\Dashboard\Index;
use App\Models\Baby;
use App\Models\BabyAction;
use App\Models\BabyActionType;
use App\Models\Plan;
use App\Models\PlanVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_shows_empty_state_when_no_babies(): void
    {
        Livewire::test(Index::class)
            ->assertSee('No children added yet')
            ->assertDontSee('No actions yet');
    }

    public function test_shows_ongoing_action_for_a_baby(): void
    {
        $baby = Baby::factory()->create(['name' => 'Emma']);
        $type = BabyActionType::factory()->create(['name' => 'Sleep']);

        BabyAction::factory()->for($baby)->create([
            'baby_action_type_id' => $type->id,
            'started_at' => now()->subHour(),
            'finished_at' => null,
        ]);

        Livewire::test(Index::class)
            ->assertSee('Emma')
            ->assertSee('Sleep')
            ->assertSee('Stop')
            ->assertDontSee('No actions yet');
    }

    public function test_shows_finished_action_without_finish_button(): void
    {
        $baby = Baby::factory()->create();
        $type = BabyActionType::factory()->create(['name' => 'Sleep']);

        BabyAction::factory()->for($baby)->create([
            'baby_action_type_id' => $type->id,
            'started_at' => now()->subHours(2),
            'finished_at' => now()->subHour(),
        ]);

        Livewire::test(Index::class)
            ->assertSee('Sleep')
            ->assertSee('ended')
            ->assertDontSee('Stop')
            ->assertDontSee('No actions yet');
    }

    public function test_shows_empty_actions_state_when_baby_has_no_actions(): void
    {
        Baby::factory()->create();

        Livewire::test(Index::class)
            ->assertSee('No actions yet');
    }

    public function test_shows_only_latest_three_actions(): void
    {
        $baby = Baby::factory()->create();

        foreach (['Oldest', 'Third', 'Second', 'Newest'] as $index => $name) {
            $type = BabyActionType::factory()->create(['name' => $name]);

            BabyAction::factory()->for($baby)->create([
                'baby_action_type_id' => $type->id,
                'started_at' => now()->subHours(10 - $index),
                'finished_at' => null,
            ]);
        }

        Livewire::test(Index::class)
            ->assertSee('Newest')
            ->assertSee('Second')
            ->assertSee('Third')
            ->assertDontSee('Oldest');
    }

    public function test_does_not_show_reminders(): void
    {
        $baby = Baby::factory()->create();
        $type = BabyActionType::factory()->create();
        $plan = Plan::factory()->create(['baby_action_type_id' => $type->id, 'all_children' => true]);
        PlanVersion::factory()->for($plan)->create();

        // Observer schedules the reminder on create, setting notification_scheduled_at.
        BabyAction::factory()->for($baby)->create([
            'baby_action_type_id' => $type->id,
            'started_at' => now()->subHour(),
            'finished_at' => null,
        ]);

        Livewire::test(Index::class)
            ->assertDontSee('Time to eat!')
            ->assertDontSee('No reminders scheduled');
    }

    public function test_shows_no_age_badge_for_baby_without_birth_date(): void
    {
        Baby::factory()->create(['name' => 'Emma', 'birth_date' => null]);

        $component = Livewire::test(Index::class)
            ->assertSee('Emma');

        $this->assertNull($component->viewData('cards')->first()['age']);
    }

    public function test_finish_now_marks_action_finished(): void
    {
        $baby = Baby::factory()->create();
        $type = BabyActionType::factory()->create();

        $action = BabyAction::factory()->for($baby)->create([
            'baby_action_type_id' => $type->id,
            'started_at' => now()->subHour(),
            'finished_at' => null,
        ]);

        Livewire::test(Index::class)
            ->call('finishNow', $action->id);

        $this->assertNotNull($action->refresh()->finished_at);
    }

    public function test_each_baby_card_links_quick_actions_prefilled_with_baby_and_type(): void
    {
        $baby = Baby::factory()->create(['name' => 'Emma']);
        $eat = BabyActionType::factory()->create(['name' => 'Eat']);

        Livewire::test(Index::class)
            ->assertSeeHtml(e(route('baby_actions.create', ['baby' => $baby->id, 'type' => $eat->id])));
    }

    public function test_a_running_action_replaces_its_quick_start_button(): void
    {
        $baby = Baby::factory()->create(['name' => 'Emma']);
        $sleep = BabyActionType::factory()->create(['name' => 'Sleep', 'is_instant' => false]);

        BabyAction::factory()->for($baby)->create([
            'baby_action_type_id' => $sleep->id,
            'started_at' => now()->subHour(),
            'finished_at' => null,
        ]);

        Livewire::test(Index::class)
            ->assertDontSeeHtml(e(route('baby_actions.create', ['baby' => $baby->id, 'type' => $sleep->id])));
    }

    public function test_finish_now_can_be_undone(): void
    {
        $baby = Baby::factory()->create();
        $type = BabyActionType::factory()->create(['is_instant' => false]);

        $action = BabyAction::factory()->for($baby)->create([
            'baby_action_type_id' => $type->id,
            'started_at' => now()->subHour(),
            'finished_at' => null,
        ]);

        Livewire::test(Index::class)
            ->call('finishNow', $action->id)
            ->assertSet('lastFinishedId', $action->id)
            ->assertSee('Undo')
            ->call('undoFinish')
            ->assertSet('lastFinishedId', null);

        $this->assertNull($action->refresh()->finished_at);
    }
}
