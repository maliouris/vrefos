<?php

namespace Tests\Feature;

use App\Enums\FoodType;
use App\Livewire\Pages\BabyAction\Create;
use App\Livewire\Pages\BabyAction\Edit;
use App\Livewire\Pages\BabyAction\Index;
use App\Models\Baby;
use App\Models\BabyAction;
use App\Models\BabyActionType;
use App\Services\PlanScheduler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BabyActionManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_prefills_baby_and_type_from_the_query_string(): void
    {
        $baby = Baby::factory()->create();
        Baby::factory()->create();
        $eat = BabyActionType::factory()->create(['name' => 'Eat']);

        Livewire::withQueryParams(['baby' => $baby->id, 'type' => $eat->id])
            ->test(Create::class)
            ->assertSet('baby_id', $baby->id)
            ->assertSet('baby_action_type_id', $eat->id);
    }

    public function test_create_selects_a_lone_baby_automatically(): void
    {
        $baby = Baby::factory()->create();

        Livewire::test(Create::class)->assertSet('baby_id', $baby->id);
    }

    public function test_create_leaves_baby_empty_when_there_are_several(): void
    {
        Baby::factory()->count(2)->create();

        Livewire::test(Create::class)->assertSet('baby_id', null);
    }

    public function test_create_ignores_unknown_query_ids(): void
    {
        Baby::factory()->count(2)->create();

        Livewire::withQueryParams(['baby' => 999, 'type' => 999])
            ->test(Create::class)
            ->assertSet('baby_id', null)
            ->assertSet('baby_action_type_id', null);
    }

    public function test_selected_baby_and_type_cannot_be_deselected(): void
    {
        $baby = Baby::factory()->create();
        $sleep = BabyActionType::factory()->create(['name' => 'Sleep']);

        Livewire::test(Create::class)
            ->call('toggleBaby', $baby->id)
            ->call('toggleBaby', $baby->id)
            ->assertSet('baby_id', $baby->id)
            ->call('toggleActionType', $sleep->id)
            ->call('toggleActionType', $sleep->id)
            ->assertSet('baby_action_type_id', $sleep->id);
    }

    public function test_save_returns_to_the_page_the_user_came_from(): void
    {
        $baby = Baby::factory()->create();
        $sleep = BabyActionType::factory()->create(['name' => 'Sleep']);

        $this->from(route('dashboard'));

        Livewire::test(Create::class)
            ->call('toggleActionType', $sleep->id)
            ->set('started_at', now()->subHour()->format('Y-m-d\TH:i'))
            ->call('save')
            ->assertRedirect(route('dashboard'));
    }

    public function test_create_persists_action_without_eat_detail_for_non_eat_type(): void
    {
        $baby = Baby::factory()->create();
        $sleep = BabyActionType::factory()->create(['name' => 'Sleep']);

        Livewire::test(Create::class)
            ->call('toggleBaby', $baby->id)
            ->call('toggleActionType', $sleep->id)
            ->set('started_at', now()->subHour()->format('Y-m-d\TH:i'))
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect();

        $action = BabyAction::firstWhere('baby_id', $baby->id);
        $this->assertNotNull($action);
        $this->assertNull($action->eatDetail);
    }

    public function test_create_eat_action_with_food_type_creates_eat_detail(): void
    {
        $baby = Baby::factory()->create();
        $eat = BabyActionType::factory()->create(['name' => 'Eat']);

        Livewire::test(Create::class)
            ->call('toggleBaby', $baby->id)
            ->call('toggleActionType', $eat->id)
            ->set('started_at', now()->subHour()->format('Y-m-d\TH:i'))
            ->call('toggleFoodType', FoodType::Formula->value)
            ->call('save')
            ->assertHasNoErrors();

        $action = BabyAction::firstWhere('baby_id', $baby->id);
        $this->assertNotNull($action->eatDetail);
        $this->assertEquals(FoodType::Formula, $action->eatDetail->food_type);
    }

    public function test_create_eat_action_without_food_type_does_not_create_eat_detail(): void
    {
        $baby = Baby::factory()->create();
        $eat = BabyActionType::factory()->create(['name' => 'Eat']);

        Livewire::test(Create::class)
            ->call('toggleBaby', $baby->id)
            ->call('toggleActionType', $eat->id)
            ->set('started_at', now()->subHour()->format('Y-m-d\TH:i'))
            ->call('save')
            ->assertHasNoErrors();

        $action = BabyAction::firstWhere('baby_id', $baby->id);
        $this->assertNull($action->eatDetail);
    }

    public function test_create_eat_action_with_toggled_food_type_does_not_create_eat_detail(): void
    {
        $baby = Baby::factory()->create();
        $eat = BabyActionType::factory()->create(['name' => 'Eat']);

        Livewire::test(Create::class)
            ->call('toggleBaby', $baby->id)
            ->call('toggleActionType', $eat->id)
            ->set('started_at', now()->subHour()->format('Y-m-d\TH:i'))
            ->call('toggleFoodType', FoodType::Formula->value)
            ->call('toggleFoodType', FoodType::Formula->value)
            ->call('save')
            ->assertHasNoErrors();

        $action = BabyAction::firstWhere('baby_id', $baby->id);
        $this->assertNull($action->eatDetail);
    }

    public function test_create_validates_finished_at_is_after_started_at(): void
    {
        $baby = Baby::factory()->create();
        $sleep = BabyActionType::factory()->create(['name' => 'Sleep']);

        Livewire::test(Create::class)
            ->call('toggleBaby', $baby->id)
            ->call('toggleActionType', $sleep->id)
            ->set('started_at', now()->format('Y-m-d\TH:i'))
            ->set('finished_at', now()->subHour()->format('Y-m-d\TH:i'))
            ->call('save')
            ->assertHasErrors('finished_at');
    }

    public function test_create_requires_baby_and_action_type(): void
    {
        Livewire::test(Create::class)
            ->set('started_at', now()->format('Y-m-d\TH:i'))
            ->call('save')
            ->assertHasErrors(['baby_id', 'baby_action_type_id']);
    }

    public function test_edit_removes_eat_detail_when_type_changes_away_from_eat(): void
    {
        $baby = Baby::factory()->create();
        $eat = BabyActionType::factory()->create(['name' => 'Eat']);
        $sleep = BabyActionType::factory()->create(['name' => 'Sleep']);

        $action = BabyAction::factory()->for($baby)->create([
            'baby_action_type_id' => $eat->id,
            'started_at' => now()->subHour(),
            'finished_at' => null,
        ]);
        $action->eatDetail()->create(['food_type' => FoodType::Formula->value]);

        Livewire::test(Edit::class, ['babyAction' => $action])
            ->call('toggleActionType', $sleep->id)
            ->call('update')
            ->assertHasNoErrors();

        $this->assertNull($action->fresh()->eatDetail);
    }

    public function test_edit_updates_existing_eat_detail(): void
    {
        $baby = Baby::factory()->create();
        $eat = BabyActionType::factory()->create(['name' => 'Eat']);

        $action = BabyAction::factory()->for($baby)->create([
            'baby_action_type_id' => $eat->id,
            'started_at' => now()->subHour(),
            'finished_at' => null,
        ]);
        $action->eatDetail()->create(['food_type' => FoodType::Formula->value]);

        Livewire::test(Edit::class, ['babyAction' => $action])
            ->call('toggleFoodType', FoodType::Vegetables->value)
            ->call('update')
            ->assertHasNoErrors();

        $this->assertEquals(FoodType::Vegetables, $action->fresh()->eatDetail->food_type);
    }

    public function test_deleting_action_cancels_notification_and_cascades_eat_detail(): void
    {
        $baby = Baby::factory()->create();
        $eat = BabyActionType::factory()->create(['name' => 'Eat']);

        $action = BabyAction::factory()->for($baby)->create([
            'baby_action_type_id' => $eat->id,
            'started_at' => now()->subHour(),
            'finished_at' => null,
        ]);
        $detail = $action->eatDetail()->create(['food_type' => FoodType::Formula->value]);

        $scheduler = $this->spy(PlanScheduler::class);

        $action->delete();

        $this->assertDatabaseMissing('baby_actions', ['id' => $action->id]);
        $this->assertDatabaseMissing('baby_action_eat_details', ['id' => $detail->id]);
        $scheduler->shouldHaveReceived('reanchorForAction')->once();
    }

    public function test_finish_now_sets_finished_at_to_current_time(): void
    {
        $baby = Baby::factory()->create();
        $sleep = BabyActionType::factory()->create(['name' => 'Sleep']);

        $action = BabyAction::factory()->for($baby)->create([
            'baby_action_type_id' => $sleep->id,
            'started_at' => now()->subHour(),
            'finished_at' => null,
        ]);

        Livewire::test(Index::class)
            ->call('finishNow', $action->id)
            ->assertHasNoErrors();

        $finishedAt = $action->fresh()->finished_at;
        $this->assertNotNull($finishedAt);
        $this->assertEqualsWithDelta(now()->timestamp, $finishedAt->timestamp, 5);
    }

    public function test_index_renders_action_cards_with_details(): void
    {
        $baby = Baby::factory()->create(['name' => 'Maria']);
        $eat = BabyActionType::factory()->create(['name' => 'Eat']);
        $sleep = BabyActionType::factory()->create(['name' => 'Sleep']);

        $ongoing = BabyAction::factory()->for($baby)->create([
            'baby_action_type_id' => $eat->id,
            'started_at' => now()->subHour(),
            'finished_at' => null,
        ]);
        $ongoing->eatDetail()->create([
            'food_type' => FoodType::BreastMilkLeft->value,
        ]);

        Livewire::test(Index::class)
            ->assertSee('Maria')
            ->assertSee('Eat')
            ->assertSeeHtml("Livewire.navigate('".route('baby_actions.edit', $ongoing)."')")
            ->assertSee(FoodType::BreastMilkLeft->label())
            ->assertSee('Finish now')
            ->assertDontSee('Total:')
            ->assertDontSee('Edit')
            ->assertDontSee('Delete');

        $ongoing->update(['finished_at' => now(), 'baby_action_type_id' => $sleep->id]);
        $ongoing->eatDetail()->delete();

        Livewire::test(Index::class)
            ->assertSee('Sleep')
            ->assertSee('Total:')
            ->assertSee('1h')
            ->assertDontSee('Finish now');
    }

    public function test_delete_removes_action_and_its_eat_detail(): void
    {
        $baby = Baby::factory()->create();
        $eat = BabyActionType::factory()->create(['name' => 'Eat']);

        $action = BabyAction::factory()->for($baby)->create([
            'baby_action_type_id' => $eat->id,
            'started_at' => now()->subHour(),
            'finished_at' => null,
        ]);
        $detail = $action->eatDetail()->create(['food_type' => FoodType::Formula->value]);

        Livewire::test(Edit::class, ['babyAction' => $action])
            ->call('delete')
            ->assertRedirect(route('baby_actions.show'));

        $this->assertDatabaseMissing('baby_actions', ['id' => $action->id]);
        $this->assertDatabaseMissing('baby_action_eat_details', ['id' => $detail->id]);
    }

    public function test_finish_now_is_noop_for_already_finished_action(): void
    {
        $baby = Baby::factory()->create();
        $sleep = BabyActionType::factory()->create(['name' => 'Sleep']);

        $finishedAt = now()->subMinutes(30);
        $action = BabyAction::factory()->for($baby)->create([
            'baby_action_type_id' => $sleep->id,
            'started_at' => now()->subHour(),
            'finished_at' => $finishedAt,
        ]);

        Livewire::test(Index::class)
            ->call('finishNow', $action->id)
            ->assertHasNoErrors();

        $this->assertEquals($finishedAt->timestamp, $action->fresh()->finished_at->timestamp);
    }
}
