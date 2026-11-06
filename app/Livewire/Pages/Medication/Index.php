<?php

namespace App\Livewire\Pages\Medication;

use App\Models\Medication;
use App\Models\MedicationCategory;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    public ?int $deletingCategoryId = null;

    public string $deleteModalTitle = '';

    public string $deleteModalMessage = '';

    public string $deleteModalType = 'confirm'; // 'confirm', 'blocked', 'warn'

    public ?array $blockingRules = null;

    public ?array $uncategorizedMedications = null;

    public bool $showAddCategoryModal = false;

    public string $newCategoryName = '';

    public function openAddCategoryModal(): void
    {
        $this->newCategoryName = '';
        $this->resetErrorBag('newCategoryName');
        $this->showAddCategoryModal = true;
    }

    public function closeAddCategoryModal(): void
    {
        $this->showAddCategoryModal = false;
    }

    public function createCategory(): void
    {
        $this->validate([
            'newCategoryName' => [
                'required',
                'string',
                'max:255',
                Rule::unique('medication_categories', 'name'),
            ],
        ]);

        MedicationCategory::create(['name' => trim($this->newCategoryName)]);

        $this->showAddCategoryModal = false;
        session()->flash('success', 'Category created.');
    }

    public function deleteCategory(int $categoryId): void
    {
        $category = MedicationCategory::findOrFail($categoryId);

        // Check if category is used by medications
        $medicationsUsingCategory = $category->medications()->get(['id', 'name']);

        if ($medicationsUsingCategory->isNotEmpty()) {
            $this->deleteModalType = 'warn';
            $this->deleteModalTitle = 'Category will be removed from medications';
            $this->deleteModalMessage = "Deleting this category will remove it from {$medicationsUsingCategory->count()} medication(s).";
            $this->uncategorizedMedications = $medicationsUsingCategory->pluck('name')->all();
            $this->deletingCategoryId = $categoryId;

            return;
        }

        // No blocking reason, proceed with delete
        $category->delete();
        session()->flash('success', 'Category deleted.');
    }

    public function confirmCategoryDelete(int $categoryId): void
    {
        $category = MedicationCategory::findOrFail($categoryId);
        $category->medications()->detach();
        $category->delete();

        $this->deletingCategoryId = null;
        session()->flash('success', 'Category deleted.');
    }

    public function closeCategoryModal(): void
    {
        $this->deletingCategoryId = null;
        $this->blockingRules = null;
        $this->uncategorizedMedications = null;
    }

    public function render()
    {
        $medications = Medication::with('categories')->withCount('actionDetails')->orderBy('name')->get();
        $categories = MedicationCategory::orderBy('name')->get();

        return view('livewire.pages.medication.index', compact('medications', 'categories'));
    }
}
