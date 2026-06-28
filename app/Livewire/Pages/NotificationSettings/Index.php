<?php

namespace App\Livewire\Pages\NotificationSettings;

use App\Enums\NotifyFrom;
use App\Models\Baby;
use App\Models\BabyActionType;
use App\Models\NotificationSetting;
use App\Services\LocalNotificationScheduler;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    public bool $showModal = false;

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

    public function openCreate(int $typeId): void
    {
        $this->resetForm();
        $this->ruleTypeId = $typeId;
        $this->title = 'Time to '.strtolower(BabyActionType::findOrFail($typeId)->name).'!';
        $this->showModal = true;
    }

    public function openEdit(int $settingId): void
    {
        $setting = NotificationSetting::findOrFail($settingId);

        $this->editingId = $setting->id;
        $this->ruleTypeId = $setting->baby_action_type_id;
        $this->notifyAfterMinutes = $setting->notify_after_minutes;
        $this->notifyFrom = $setting->notify_from->value;
        $this->title = $setting->title;
        $this->description = $setting->description;
        $this->enabled = $setting->enabled;
        $this->allChildren = $setting->all_children;
        $this->targetBabyIds = $setting->all_children ? [] : $setting->babies->pluck('id')->all();
        $this->showModal = true;
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
    }

    public function render()
    {
        return view('livewire.pages.notification-settings.index', [
            'actionTypes' => BabyActionType::with('notificationSettings.babies:id,name')->orderBy('name')->get(),
            'babies' => Baby::orderBy('name')->get(['id', 'name']),
        ]);
    }
}
