<?php

namespace Tests\Feature;

use App\Enums\NotifyFrom;
use App\Livewire\Pages\Dashboard\Index;
use App\Models\Baby;
use App\Models\BabyAction;
use App\Models\BabyActionType;
use App\Models\NotificationSetting;
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
            ->assertSee('Finish now')
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
            ->assertDontSee('Finish now')
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
        NotificationSetting::factory()->create([
            'baby_action_type_id' => $type->id,
            'enabled' => true,
            'notify_after_minutes' => 180,
            'notify_from' => NotifyFrom::StartedAt,
            'title' => 'Time to eat!',
        ]);

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
}
