<?php

namespace App\Livewire\Pages\NotificationSettings;

use App\Enums\FeverLevel;
use App\Enums\NotifyFrom;
use App\Livewire\Concerns\HandlesNotificationPermission;
use App\Models\Baby;
use App\Models\BabyActionType;
use App\Models\Medication;
use App\Models\MedicationCategory;
use App\Models\NotificationSetting;
use App\Services\LocalNotificationScheduler;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    use HandlesNotificationPermission;

    public bool $showModal = false;

    public ?int $deletingRuleId = null;

    public ?int $editingId = null;

    public ?int $ruleTypeId = null;

    public int $notifyAfterMinutes = 180;

    public string $notifyFrom = 'started_at';

    public string $title = '';

    public ?string $description = null;

    public bool $enabled = true;

    public bool $allChildren = true;

    /** @var array<int, int> */
    public array $targetBabyIds = [];

    /** @var array<string> */
    public array $conditionFeverLevels = [];

    /** @var array<int> */
    public array $conditionMedicationIds = [];

    /** @var array<int> */
    public array $conditionCategoryIds = [];

    /** @var array<int> */
    public array $excludedMedicationIds = [];

    public function openCreate(int $typeId): void
    {
        $this->resetForm();
        $this->ruleTypeId = $typeId;
        $type = BabyActionType::findOrFail($typeId);
        $this->title = $type->is_instant ? 'Check on #{baby}!' : 'Time to '.strtolower($type->name).'!';
        $this->showModal = true;
    }

    public function openEdit(int $settingId): void
    {
        $setting = NotificationSetting::with(['feverLevelConditions', 'targetMedications:id', 'excludedMedications:id', 'medicationCategories:id'])->findOrFail($settingId);

        $this->editingId = $setting->id;
        $this->ruleTypeId = $setting->baby_action_type_id;
        $this->notifyAfterMinutes = $setting->notify_after_minutes;
        $this->notifyFrom = $setting->notify_from->value;
        $this->title = $setting->title;
        $this->description = $setting->description;
        $this->enabled = $setting->enabled;
        $this->allChildren = $setting->all_children;
        $this->targetBabyIds = $setting->all_children ? [] : $setting->babies->pluck('id')->all();
        $this->conditionFeverLevels = $setting->feverLevelConditions->pluck('fever_level')->map(fn ($level) => $level->value)->all();
        $this->conditionMedicationIds = $setting->targetMedications->pluck('id')->all();
        $this->conditionCategoryIds = $setting->medicationCategories->pluck('id')->all();
        $this->excludedMedicationIds = $setting->excludedMedications->pluck('id')->all();
        $this->showModal = true;
    }

    public function promptDeleteRule(int $settingId): void
    {
        // Closing the edit modal avoids the delete-confirm overlay (z-40)
        // rendering behind the daisyUI modal (higher z-index).
        $this->showModal = false;
        $this->deletingRuleId = $settingId;
    }

    public function closeDeleteModal(): void
    {
        $this->deletingRuleId = null;
    }

    public function toggleAllChildren(): void
    {
        $this->allChildren = true;
        $this->targetBabyIds = [];
    }

    public function toggleBaby(int $babyId): void
    {
        if (in_array($babyId, $this->targetBabyIds, true)) {
            $this->targetBabyIds = array_values(array_diff($this->targetBabyIds, [$babyId]));
        } else {
            $this->targetBabyIds[] = $babyId;
        }

        // An empty selection means "all children"; otherwise targeting is specific.
        $this->allChildren = $this->targetBabyIds === [];
    }

    public function toggleConditionFeverLevel(string $level): void
    {
        if (in_array($level, $this->conditionFeverLevels, true)) {
            $this->conditionFeverLevels = array_values(array_diff($this->conditionFeverLevels, [$level]));
        } else {
            $this->conditionFeverLevels[] = $level;
        }
    }

    public function toggleConditionMedication(int $medicationId): void
    {
        if (in_array($medicationId, $this->conditionMedicationIds, true)) {
            $this->conditionMedicationIds = array_values(array_diff($this->conditionMedicationIds, [$medicationId]));
        } else {
            $this->conditionMedicationIds[] = $medicationId;
            $this->excludedMedicationIds = array_values(array_diff($this->excludedMedicationIds, [$medicationId]));
        }
    }

    public function toggleConditionCategory(int $categoryId): void
    {
        if (in_array($categoryId, $this->conditionCategoryIds, true)) {
            $this->conditionCategoryIds = array_values(array_diff($this->conditionCategoryIds, [$categoryId]));
        } else {
            $this->conditionCategoryIds[] = $categoryId;
        }
    }

    public function toggleExcludedMedication(int $medicationId): void
    {
        if (in_array($medicationId, $this->excludedMedicationIds, true)) {
            $this->excludedMedicationIds = array_values(array_diff($this->excludedMedicationIds, [$medicationId]));
        } else {
            $this->excludedMedicationIds[] = $medicationId;
            $this->conditionMedicationIds = array_values(array_diff($this->conditionMedicationIds, [$medicationId]));
        }
    }

    public function saveRule(LocalNotificationScheduler $scheduler): void
    {
        $this->validate([
            'ruleTypeId' => ['required', 'exists:baby_action_types,id'],
            'notifyAfterMinutes' => 'required|integer|min:1|max:10080',
            'notifyFrom' => ['required', Rule::enum(NotifyFrom::class)],
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:255',
            'enabled' => 'boolean',
            'allChildren' => 'boolean',
            'targetBabyIds' => [Rule::requiredIf(! $this->allChildren), 'array'],
            'targetBabyIds.*' => 'exists:babies,id',
            'conditionFeverLevels' => 'array',
            'conditionFeverLevels.*' => [Rule::enum(FeverLevel::class)],
            'conditionMedicationIds' => 'array',
            'conditionMedicationIds.*' => 'exists:medications,id',
            'conditionCategoryIds' => 'array',
            'conditionCategoryIds.*' => 'exists:medication_categories,id',
            'excludedMedicationIds' => 'array',
            'excludedMedicationIds.*' => 'exists:medications,id',
        ]);

        $type = BabyActionType::findOrFail($this->ruleTypeId);

        $attributes = [
            'enabled' => $this->enabled,
            'notify_after_minutes' => $this->notifyAfterMinutes,
            'notify_from' => $this->notifyFrom,
            'title' => $this->title,
            'description' => $this->description,
            'all_children' => $this->allChildren,
        ];

        if ($this->editingId !== null) {
            $setting = NotificationSetting::findOrFail($this->editingId);
            $setting->update($attributes);
        } else {
            $setting = NotificationSetting::create($attributes + ['baby_action_type_id' => $type->id]);
        }

        $babyIds = $this->allChildren ? Baby::pluck('id')->all() : $this->targetBabyIds;
        $setting->babies()->sync($babyIds);

        // Sync condition rows: delete existing and create new ones per condition type
        $setting->feverLevelConditions()->delete();
        foreach ($this->conditionFeverLevels as $level) {
            $setting->feverLevelConditions()->create(['fever_level' => $level]);
        }

        // Medication targeting: one sync call with ['excluded' => bool] pivot values
        $medicationSync = [];
        foreach (array_merge($this->conditionMedicationIds, $this->excludedMedicationIds) as $medId) {
            $medicationSync[$medId] = ['excluded' => in_array($medId, $this->excludedMedicationIds, true)];
        }
        $setting->targetMedications()->sync($medicationSync);

        // Category targeting
        $setting->medicationCategories()->sync($this->conditionCategoryIds);

        $scheduler->rescheduleAllForType($type);

        $this->showModal = false;
        $this->resetForm();

        session()->flash('success', 'Notification rule saved.');
    }

    public function deleteRule(int $settingId, LocalNotificationScheduler $scheduler): void
    {
        $setting = NotificationSetting::findOrFail($settingId);
        $type = $setting->babyActionType;

        $setting->delete();

        $scheduler->rescheduleAllForType($type);

        $this->deletingRuleId = null;
        $this->resetForm();

        session()->flash('success', 'Notification rule deleted.');
    }

    public function toggleEnabled(int $settingId, LocalNotificationScheduler $scheduler): void
    {
        $setting = NotificationSetting::findOrFail($settingId);
        $setting->update(['enabled' => ! $setting->enabled]);

        $scheduler->rescheduleAllForType($setting->babyActionType);
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->ruleTypeId = null;
        $this->notifyAfterMinutes = 180;
        $this->notifyFrom = 'started_at';
        $this->title = '';
        $this->description = null;
        $this->enabled = true;
        $this->allChildren = true;
        $this->targetBabyIds = [];
        $this->conditionFeverLevels = [];
        $this->conditionMedicationIds = [];
        $this->conditionCategoryIds = [];
        $this->excludedMedicationIds = [];
    }

    public function render()
    {
        return view('livewire.pages.notification-settings.index', [
            'actionTypes' => BabyActionType::with('notificationSettings.babies:id,name')->orderBy('name')->get(),
            'babies' => Baby::orderBy('name')->get(['id', 'name']),
            'feverLevels' => FeverLevel::cases(),
            'medications' => Medication::orderBy('name')->get(['id', 'name']),
            'medicationCategories' => MedicationCategory::orderBy('name')->get(['id', 'name']),
        ]);
    }
}
