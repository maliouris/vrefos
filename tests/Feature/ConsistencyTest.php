<?php

namespace Tests\Feature;

use App\Livewire\Pages\BabyAction\Edit as BabyActionEdit;
use App\Livewire\Pages\Medication\Index as MedicationIndex;
use App\Models\BabyAction;
use App\Models\BabyActionType;
use App\Models\Medication;
use App\Models\MedicationCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_an_unused_category_asks_for_confirmation_first(): void
    {
        $category = MedicationCategory::create(['name' => 'Vitamins']);

        $component = Livewire::test(MedicationIndex::class)
            ->call('deleteCategory', $category->id)
            ->assertSet('deletingCategoryId', $category->id)
            ->assertSee('Delete category?');

        $this->assertDatabaseHas('medication_categories', ['id' => $category->id]);

        $component->call('confirmCategoryDelete', $category->id)
            ->assertSet('deletingCategoryId', null);

        $this->assertDatabaseMissing('medication_categories', ['id' => $category->id]);
    }

    public function test_cancelling_the_category_dialog_keeps_the_category(): void
    {
        $category = MedicationCategory::create(['name' => 'Vitamins']);

        Livewire::test(MedicationIndex::class)
            ->call('deleteCategory', $category->id)
            ->call('closeCategoryModal')
            ->assertSet('deletingCategoryId', null);

        $this->assertDatabaseHas('medication_categories', ['id' => $category->id]);
    }

    public function test_a_used_category_lists_the_medications_that_lose_it(): void
    {
        $category = MedicationCategory::create(['name' => 'Vitamins']);
        $medication = Medication::create(['name' => 'Vitamin D']);
        $medication->categories()->attach($category);

        Livewire::test(MedicationIndex::class)
            ->call('deleteCategory', $category->id)
            ->assertSee('Medications losing this category:')
            ->assertSee('Vitamin D');
    }

    public function test_deleting_a_baby_action_asks_for_confirmation_in_a_modal(): void
    {
        $action = BabyAction::factory()->create();

        Livewire::test(BabyActionEdit::class, ['babyAction' => $action])
            ->assertSet('showDeleteModal', false)
            ->call('promptDelete')
            ->assertSet('showDeleteModal', true)
            ->assertSee('Delete this action?');

        $this->assertDatabaseHas('baby_actions', ['id' => $action->id]);
    }

    public function test_action_type_icons_come_from_one_place(): void
    {
        $this->assertSame('o-cake', BabyActionType::iconFor('Eat'));
        $this->assertSame('o-moon', BabyActionType::iconFor('sleep'));
        $this->assertSame('o-fire', BabyActionType::iconFor('Temperature'));
        $this->assertSame('o-beaker', BabyActionType::iconFor('Medication'));
        $this->assertSame('o-clock', BabyActionType::iconFor('Something else'));
        $this->assertSame('o-moon', BabyActionType::factory()->make(['name' => 'Sleep'])->icon());
    }
}
