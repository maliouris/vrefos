<?php

namespace App\Livewire\Pages\NotificationSettings;

use App\Enums\NotifyFrom;
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

    public ?string $message = null;

    public bool $enabled = true;

    public function openCreate(int $typeId): void
    {
        $this->resetForm();
        $this->ruleTypeId = $typeId;
        $this->showModal = true;
    }

    public function openEdit(int $settingId): void
    {
        $setting = NotificationSetting::findOrFail($settingId);

        $this->editingId = $setting->id;
        $this->ruleTypeId = $setting->baby_action_type_id;
        $this->notifyAfterMinutes = $setting->notify_after_minutes;
        $this->notifyFrom = $setting->notify_from->value;
        $this->message = $setting->message;
        $this->enabled = $setting->enabled;
        $this->showModal = true;
    }

    public function saveRule(LocalNotificationScheduler $scheduler): void
    {
        $this->validate([
            'ruleTypeId' => ['required', 'exists:baby_action_types,id'],
            'notifyAfterMinutes' => 'required|integer|min:1|max:10080',
            'notifyFrom' => ['required', Rule::enum(NotifyFrom::class)],
            'message' => 'nullable|string|max:255',
            'enabled' => 'boolean',
        ]);

        $type = BabyActionType::findOrFail($this->ruleTypeId);

        $attributes = [
            'enabled' => $this->enabled,
            'notify_after_minutes' => $this->notifyAfterMinutes,
            'notify_from' => $this->notifyFrom,
            'message' => $this->message,
        ];

        if ($this->editingId !== null) {
            NotificationSetting::findOrFail($this->editingId)->update($attributes);
        } else {
            NotificationSetting::create($attributes + ['baby_action_type_id' => $type->id]);
        }

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
        $this->message = null;
        $this->enabled = true;
    }

    public function render()
    {
        return view('livewire.pages.notification-settings.index', [
            'actionTypes' => BabyActionType::with('notificationSettings')->orderBy('name')->get(),
        ]);
    }
}
