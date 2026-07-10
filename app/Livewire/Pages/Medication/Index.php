<?php

namespace App\Livewire\Pages\Medication;

use App\Models\BabyActionType;
use App\Models\Medication;
use App\Models\MedicationCategory;
use App\Models\NotificationSetting;
use App\Services\LocalNotificationScheduler;
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

    public function deleteCategory(int $categoryId, LocalNotificationScheduler $scheduler): void
    {
        $category = MedicationCategory::findOrFail($categoryId);

        // Check if category is used by notification rules
        $rulesUsingCategory = NotificationSetting::whereHas('medicationCategories', fn ($q) => $q->where('medication_category_id', $categoryId))
            ->with('babyActionType:id,name')
            ->get(['id', 'baby_action_type_id', 'title', 'notify_after_minutes']);

        if ($rulesUsingCategory->isNotEmpty()) {
            $this->deleteModalType = 'blocked';
            $this->deleteModalTitle = 'Cannot delete category';
            $this->deleteModalMessage = "This category is used by {$rulesUsingCategory->count()} notification rule(s). Change or delete those rules first.";
            $this->blockingRules = $rulesUsingCategory->map(fn ($r) => [
                'title' => $r->title,
                'type' => $r->babyActionType->name,
                'delay' => "{$r->notify_after_minutes} min",
            ])->all();
            $this->deletingCategoryId = $categoryId;

            return;
        }

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

        $scheduler = app(LocalNotificationScheduler::class);
        $scheduler->rescheduleAllForType(BabyActionType::where('name', 'Medication')->firstOrFail());

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
