<?php

namespace Tests\Feature;

use App\Livewire\Pages\Planning\Index;
use App\Models\BabyActionType;
use App\Models\Notification;
use App\Models\Plan;
use App\Models\PlanVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PlanningFixesTest extends TestCase
{
    use RefreshDatabase;

    public function test_start_and_end_message_inputs_are_independent(): void
    {
        $type = BabyActionType::factory()->create();

        Livewire::test(Index::class)
            ->set('babyActionTypeId', $type->id)
            ->set('durationMinutes', '30')
            ->set('newStartMessageTitle', 'Begin')
            ->assertSet('newEndMessageTitle', '')
            ->call('createAndAttachMessage', 'start')
            ->assertSet('newStartMessageTitle', '')
            ->set('newEndMessageTitle', 'Finish')
            ->assertSet('newStartMessageTitle', '')
            ->call('createAndAttachMessage', 'end')
            ->assertSet('newEndMessageTitle', '');

        $this->assertDatabaseHas('notifications', ['title' => 'Begin']);
        $this->assertDatabaseHas('notifications', ['title' => 'Finish']);
    }

    public function test_an_empty_message_title_is_rejected_for_the_matching_trigger(): void
    {
        Livewire::test(Index::class)
            ->set('newStartMessageTitle', 'Only start is filled')
            ->call('createAndAttachMessage', 'end')
            ->assertHasErrors(['newEndMessageTitle' => 'required']);
    }

    public function test_the_interval_minimum_matches_the_input_minimum(): void
    {
        $type = BabyActionType::factory()->create();

        Livewire::test(Index::class)
            ->set('babyActionTypeId', $type->id)
            ->set('intervalValue', 0.3)
            ->call('savePlan')
            ->assertHasErrors(['intervalValue' => 'min']);

        Livewire::test(Index::class)
            ->set('babyActionTypeId', $type->id)
            ->set('intervalValue', 0.5)
            ->call('savePlan')
            ->assertHasNoErrors();
    }

    public function test_the_default_anchor_date_is_the_device_local_date(): void
    {
        $this->travelTo('2026-06-10 15:00:00');

        Livewire::test(Index::class)
            ->assertSet('anchorDate', '2026-06-10')
            ->call('setDeviceTimezone', 'Pacific/Kiritimati')
            ->assertSet('anchorDate', '2026-06-11')
            ->call('openCreate')
            ->assertSet('anchorDate', '2026-06-11');
    }

    public function test_the_modal_title_says_new_or_edit(): void
    {
        $plan = Plan::factory()->create();
        PlanVersion::factory()->for($plan)->create();

        Livewire::test(Index::class)
            ->call('openCreate')
            ->assertSee('New plan')
            ->call('openEdit', $plan->id)
            ->assertSee('Edit plan');
    }

    public function test_a_plan_without_messages_shows_a_no_reminders_warning(): void
    {
        $type = BabyActionType::factory()->create();

        Livewire::test(Index::class)
            ->call('openCreate')
            ->assertSee('This plan only shows on the calendar')
            ->set('babyActionTypeId', $type->id)
            ->set('startNotificationIds', [Notification::factory()->create()->id])
            ->assertDontSee('This plan only shows on the calendar');
    }

    public function test_plan_cards_flag_plans_that_have_no_messages(): void
    {
        $plan = Plan::factory()->create();
        PlanVersion::factory()->for($plan)->create();

        // The badge is matched by its classes: the (closed) editor modal also contains the words.
        Livewire::test(Index::class)->assertSeeHtml('badge badge-warning badge-outline');

        $plan->startNotifications()->attach(Notification::factory()->create()->id, ['trigger' => 'start']);

        Livewire::test(Index::class)->assertDontSeeHtml('badge badge-warning badge-outline');
    }

    public function test_the_plan_editor_does_not_close_on_a_stray_tap(): void
    {
        $html = Livewire::test(Index::class)->call('openCreate')->html();

        // Mary's persistent modal drops the Escape handler for `showModal` and the backdrop-close form.
        $this->assertStringNotContainsString('showModal', $this->escapeHandlers($html));
        $this->assertSame(1, substr_count($html, 'modal-backdrop'), 'only the non-persistent event sheet has a backdrop');
    }

    private function escapeHandlers(string $html): string
    {
        preg_match_all('/keydown\.escape\.window\s*=\s*"[^"]*"/', html_entity_decode($html), $matches);

        return implode("\n", $matches[0]);
    }
}
