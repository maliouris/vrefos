<?php

namespace Tests\Feature;

use App\Enums\BreastSide;
use App\Enums\FoodType;
use App\Enums\NotifyFrom;
use App\Livewire\Pages\BabyAction\Create;
use App\Livewire\Pages\BabyAction\Edit;
use App\Livewire\Pages\BabyAction\Index;
use App\Models\Baby;
use App\Models\BabyAction;
use App\Models\BabyActionType;
use App\Models\NotificationSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BabyActionManagementTest extends TestCase
{
    use RefreshDatabase;

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
            ->assertRedirect(route('baby_actions.show'));

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
        $this->assertNull($action->eatDetail->breast_side);
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

    public function test_breast_side_is_cleared_when_food_type_is_not_breast_milk(): void
    {
        $baby = Baby::factory()->create();
        $eat = BabyActionType::factory()->create(['name' => 'Eat']);

        Livewire::test(Create::class)
            ->call('toggleBaby', $baby->id)
            ->call('toggleActionType', $eat->id)
            ->call('toggleFoodType', FoodType::BreastMilk->value)
            ->call('toggleBreastSide', BreastSide::Left->value)
            ->call('toggleFoodType', FoodType::Formula->value)
            ->assertSet('breast_side', null);
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

        NotificationSetting::create([
            'baby_action_type_id' => $eat->id,
            'enabled' => true,
            'notify_after_minutes' => 180,
            'notify_from' => NotifyFrom::StartedAt,
        ]);

        $action = BabyAction::factory()->for($baby)->create([
            'baby_action_type_id' => $eat->id,
            'started_at' => now()->subHour(),
            'finished_at' => null,
        ]);
        $detail = $action->eatDetail()->create(['food_type' => FoodType::Formula->value]);

        $this->assertNotNull($action->fresh()->notification_scheduled_at);

        $action->delete();

        $this->assertDatabaseMissing('baby_action_eat_details', ['id' => $detail->id]);
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
