<?php

namespace App\Livewire\Pages\Medication;

use App\Models\Medication;
use App\Models\MedicationCategory;
use App\Models\NotificationSetting;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Edit extends Component
{
    public Medication $medication;

    public string $name = '';

    /** @var array<int> */
    public array $categoryIds = [];

    public string $newCategoryName = '';

    public bool $showDeleteModal = false;

    public string $deleteModalType = 'confirm'; // 'confirm', 'blocked'

    public ?string $deleteBlockReason = null;

    public ?array $blockingRules = null;

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

        session()->flash('success', 'Medication updated.');
    }

    public function promptDelete(): void
    {
        // Check if rules reference this medication (either as target or exclusion)
        $rulesUsingMedication = NotificationSetting::where(function ($q) {
            $q->whereHas('targetMedications', fn ($sq) => $sq->where('medication_id', $this->medication->id))
                ->orWhereHas('excludedMedications', fn ($sq) => $sq->where('medication_id', $this->medication->id));
        })
            ->with('babyActionType:id,name')
            ->get(['id', 'baby_action_type_id', 'title', 'notify_after_minutes']);

        if ($rulesUsingMedication->isNotEmpty()) {
            $this->deleteModalType = 'blocked';
            $this->deleteBlockReason = "This medication is referenced by {$rulesUsingMedication->count()} notification rule(s). Change or delete those rules first.";
            $this->blockingRules = $rulesUsingMedication->map(fn ($r) => [
                'title' => $r->title,
                'type' => $r->babyActionType->name,
                'delay' => "{$r->notify_after_minutes} min",
            ])->all();
            $this->showDeleteModal = true;

            return;
        }

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
        session()->flash('success', 'Medication deleted.');
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
