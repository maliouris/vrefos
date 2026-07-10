<?php

namespace Tests\Feature;

use App\Livewire\Pages\Medication\Create;
use App\Livewire\Pages\Medication\Index;
use App\Models\Medication;
use App\Models\MedicationCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MedicationManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_persists_medication_and_redirects(): void
    {
        Livewire::test(Create::class)
            ->set('name', 'Amoxicillin')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('medications.show'));

        $this->assertDatabaseHas('medications', ['name' => 'Amoxicillin']);
    }

    public function test_create_requires_name(): void
    {
        Livewire::test(Create::class)
            ->call('save')
            ->assertHasErrors(['name']);
    }

    public function test_create_rejects_duplicate_name(): void
    {
        Medication::factory()->create(['name' => 'Amoxicillin']);

        Livewire::test(Create::class)
            ->set('name', 'Amoxicillin')
            ->call('save')
            ->assertHasErrors(['name']);
    }

    public function test_create_attaches_selected_categories(): void
    {
        $category = MedicationCategory::factory()->create(['name' => 'Antibiotic']);

        Livewire::test(Create::class)
            ->set('name', 'Amoxicillin')
            ->call('toggleCategory', $category->id)
            ->call('save')
            ->assertHasNoErrors();

        $medication = Medication::where('name', 'Amoxicillin')->firstOrFail();
        $this->assertTrue($medication->categories->contains($category));
    }

    public function test_create_creates_and_attaches_typed_new_category(): void
    {
        Livewire::test(Create::class)
            ->set('name', 'Amoxicillin')
            ->set('newCategoryName', 'Antibiotic')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('medication_categories', ['name' => 'Antibiotic']);

        $medication = Medication::where('name', 'Amoxicillin')->firstOrFail();
        $this->assertTrue($medication->categories->pluck('name')->contains('Antibiotic'));
    }

    public function test_index_create_category_persists_category(): void
    {
        Livewire::test(Index::class)
            ->set('newCategoryName', 'Antibiotic')
            ->call('createCategory')
            ->assertHasNoErrors()
            ->assertSet('showAddCategoryModal', false);

        $this->assertDatabaseHas('medication_categories', ['name' => 'Antibiotic']);
    }

    public function test_index_create_category_requires_name(): void
    {
        Livewire::test(Index::class)
            ->call('createCategory')
            ->assertHasErrors(['newCategoryName']);
    }

    public function test_index_create_category_rejects_duplicate_name(): void
    {
        MedicationCategory::factory()->create(['name' => 'Antibiotic']);

        Livewire::test(Index::class)
            ->set('newCategoryName', 'Antibiotic')
            ->call('createCategory')
            ->assertHasErrors(['newCategoryName']);
    }

    public function test_index_open_add_category_modal_resets_stale_input(): void
    {
        Livewire::test(Index::class)
            ->set('newCategoryName', 'Stale')
            ->call('openAddCategoryModal')
            ->assertSet('newCategoryName', '')
            ->assertSet('showAddCategoryModal', true);
    }
}
