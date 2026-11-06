<?php

namespace App\Livewire\Pages\Medication;

use App\Models\Medication;
use App\Models\MedicationCategory;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Mary\Traits\Toast;

#[Layout('layouts.app')]
class Edit extends Component
{
    use Toast;

    public Medication $medication;

    public string $name = '';

    /** @var array<int> */
    public array $categoryIds = [];

    public string $newCategoryName = '';

    public bool $showDeleteModal = false;

    public string $deleteModalType = 'confirm'; // 'confirm', 'blocked'

    public ?string $deleteBlockReason = null;

    public function mount(Medication $medication): void
    {
        $this->medication = $medication;
        $this->name = $medication->name;
        $this->categoryIds = $medication->categories()->pluck('id')->all();
    }

    public function save(): void
    {
        $this->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('medications', 'name')->ignore($this->medication->id),
            ],
            'categoryIds' => 'array',
            'categoryIds.*' => 'exists:medication_categories,id',
        ]);

        $this->medication->update(['name' => trim($this->name)]);

        $categoryIds = collect($this->categoryIds);

        if (trim($this->newCategoryName) !== '') {
            $categoryIds->push(MedicationCategory::firstOrCreate(['name' => trim($this->newCategoryName)])->id);
        }

        $this->medication->categories()->sync($categoryIds->unique()->all());

        $this->success('Medication updated.');
    }

    public function promptDelete(): void
    {
        // Check if actions reference this medication (FK restrict will block, but warn anyway)
        if ($this->medication->actionDetails()->exists()) {
            $this->deleteModalType = 'blocked';
            $this->deleteBlockReason = 'This medication is used in logged actions and cannot be deleted.';
            $this->showDeleteModal = true;

            return;
        }

        $this->deleteModalType = 'confirm';
        $this->showDeleteModal = true;
    }

    public function confirmDelete(): void
    {
        $this->medication->delete();
        $this->success('Medication deleted.');
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

        return view('livewire.pages.medication.edit', compact('categories'));
    }
}
