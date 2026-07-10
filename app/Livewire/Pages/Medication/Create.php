<?php

namespace App\Livewire\Pages\Medication;

use App\Models\Medication;
use App\Models\MedicationCategory;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Create extends Component
{
    public string $name = '';

    /** @var array<int> */
    public array $categoryIds = [];

    public string $newCategoryName = '';

    public function save(): void
    {
        $this->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('medications', 'name'),
            ],
            'categoryIds' => 'array',
            'categoryIds.*' => 'exists:medication_categories,id',
        ]);

        $medication = Medication::create(['name' => trim($this->name)]);

        $categoryIds = collect($this->categoryIds);

        if (trim($this->newCategoryName) !== '') {
            $categoryIds->push(MedicationCategory::firstOrCreate(['name' => trim($this->newCategoryName)])->id);
        }

        $medication->categories()->sync($categoryIds->unique()->all());

        session()->flash('success', 'Medication created.');

        $this->redirectRoute('medications.show', navigate: true);
    }

    public function toggleCategory(int $categoryId): void
    {
        if (in_array($categoryId, $this->categoryIds, true)) {
            $this->categoryIds = array_values(array_diff($this->categoryIds, [$categoryId]));
        } else {
            $this->categoryIds[] = $categoryId;
        }
    }

    public function render()
    {
        $categories = MedicationCategory::orderBy('name')->get();

        return view('livewire.pages.medication.create', compact('categories'));
    }
}
