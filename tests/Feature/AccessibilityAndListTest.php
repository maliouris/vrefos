<?php

namespace Tests\Feature;

use App\Livewire\Pages\Baby\Index as BabyIndex;
use App\Livewire\Pages\BabyAction\Create;
use App\Livewire\Pages\BabyAction\Index;
use App\Livewire\Pages\Medication\Index as MedicationIndex;
use App\Livewire\Pages\Planning\Index as PlanningIndex;
use App\Models\Baby;
use App\Models\BabyAction;
use App\Models\BabyActionType;
use App\Models\MedicationCategory;
use App\Models\Plan;
use App\Models\PlanVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AccessibilityAndListTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_the_action_list_can_be_filtered_by_baby_and_type(): void
    {
        $maria = Baby::factory()->create(['name' => 'Maria']);
        $theo = Baby::factory()->create(['name' => 'Theo']);
        $eat = BabyActionType::factory()->create(['name' => 'Eat']);
        $sleep = BabyActionType::factory()->create(['name' => 'Sleep']);

        BabyAction::factory()->for($maria)->create(['baby_action_type_id' => $eat->id]);
        BabyAction::factory()->for($theo)->create(['baby_action_type_id' => $sleep->id]);

        $component = Livewire::test(Index::class);
        $this->assertCount(2, $component->viewData('babyActions'));

        $component->call('filterByBaby', $maria->id);
        $this->assertSame([$maria->id], $component->viewData('babyActions')->pluck('baby_id')->unique()->all());

        $component->call('filterByType', $sleep->id);
        $this->assertCount(0, $component->viewData('babyActions'));
        $component->assertSee('No actions match these filters');

        // Tapping the active baby chip again clears it; the Sleep filter stays.
        $component->call('filterByBaby', $maria->id);
        $this->assertSame([$theo->id], $component->viewData('babyActions')->pluck('baby_id')->all());
    }

    public function test_the_action_list_pages_with_load_more(): void
    {
        $baby = Baby::factory()->create();
        $type = BabyActionType::factory()->create();
        BabyAction::factory()->for($baby)->count(25)->create(['baby_action_type_id' => $type->id]);

        $component = Livewire::test(Index::class);
        $this->assertCount(20, $component->viewData('babyActions'));
        $component->assertSee('Load more');

        $component->call('loadMore');
        $this->assertCount(25, $component->viewData('babyActions'));
        $component->assertDontSee('Load more');
    }

    public function test_changing_a_filter_resets_the_page_size(): void
    {
        $baby = Baby::factory()->create();

        Livewire::test(Index::class)
            ->call('loadMore')
            ->assertSet('limit', 40)
            ->call('filterByBaby', $baby->id)
            ->assertSet('limit', 20);
    }

    public function test_the_terms_placeholder_page_is_gone(): void
    {
        $this->get('/terms-and-conditions')->assertNotFound();
    }

    public function test_the_layout_works_offline_and_edge_to_edge(): void
    {
        $html = $this->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('viewport-fit=cover', $html);
        $this->assertStringNotContainsString('fonts.bunny.net', $html);
    }

    public function test_clickable_cards_are_keyboard_reachable(): void
    {
        Baby::factory()->create();

        $html = Livewire::test(BabyIndex::class)->html();

        $this->assertStringContainsString('role="link"', $html);
        $this->assertStringContainsString('tabindex="0"', $html);
        $this->assertStringContainsString('@keydown.enter.self', $html);
    }

    public function test_icon_only_controls_have_accessible_names(): void
    {
        MedicationCategory::create(['name' => 'Vitamins']);
        $plan = Plan::factory()->create();
        PlanVersion::factory()->for($plan)->create();

        $this->assertStringContainsString('aria-label="Delete category Vitamins"', Livewire::test(MedicationIndex::class)->html());

        // The delete button needs an existing plan; remove-time needs a clock-based unit with 2+ times.
        $html = Livewire::test(PlanningIndex::class)
            ->call('openEdit', $plan->id)
            ->set('intervalUnit', 'days')
            ->set('times', ['08:00', '20:00'])
            ->html();
        $this->assertStringContainsString('aria-label="Delete plan"', $html);
        $this->assertStringContainsString('aria-label="Remove time"', $html);
    }

    public function test_segmented_buttons_expose_their_pressed_state(): void
    {
        $baby = Baby::factory()->create();
        Baby::factory()->create();

        $html = Livewire::test(Create::class)->call('toggleBaby', $baby->id)->html();

        $this->assertStringContainsString('aria-pressed="true"', $html);
        $this->assertStringContainsString('aria-pressed="false"', $html);
    }

    public function test_datetime_inputs_are_linked_to_their_labels(): void
    {
        $html = Livewire::test(Create::class)->html();

        $this->assertStringContainsString('for="started-at"', $html);
        $this->assertStringContainsString('id="started-at"', $html);
    }
}
