<?php

namespace App\Livewire\Pages\NotificationSettings;

use App\Enums\NotifyFrom;
use App\Models\BabyActionType;
use App\Models\NotificationSetting;
use App\Services\LocalNotificationScheduler;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    public array $settings = [];

    public function mount(): void
    {
        $actionTypes = BabyActionType::all();

        foreach ($actionTypes as $actionType) {
            $setting = NotificationSetting::firstOrCreate(
                ['baby_action_type_id' => $actionType->id],
                ['enabled' => true, 'notify_after_minutes' => 180, 'notify_from' => NotifyFrom::StartedAt]
            );

            $this->settings[$actionType->id] = [
                'name' => $actionType->name,
                'enabled' => $setting->enabled,
                'notify_after_minutes' => $setting->notify_after_minutes,
                'notify_from' => $setting->notify_from->value,
            ];
        }
    }

    public function save(LocalNotificationScheduler $scheduler): void
    {
        foreach ($this->settings as $actionTypeId => $data) {
            $this->validate([
                "settings.{$actionTypeId}.notify_after_minutes" => 'required|integer|min:1|max:10080',
                "settings.{$actionTypeId}.notify_from" => 'required|in:started_at,finished_at',
            ]);

            $setting = NotificationSetting::firstWhere('baby_action_type_id', $actionTypeId);
            $wasChanged = $setting->enabled !== $data['enabled']
                || $setting->notify_after_minutes !== $data['notify_after_minutes']
                || $setting->notify_from->value !== $data['notify_from'];

            NotificationSetting::where('baby_action_type_id', $actionTypeId)
                ->update([
                    'enabled' => $data['enabled'],
                    'notify_after_minutes' => $data['notify_after_minutes'],
                    'notify_from' => $data['notify_from'],
                ]);

            if ($wasChanged) {
                $actionType = BabyActionType::find($actionTypeId);
                if ($data['enabled']) {
                    $count = $scheduler->rescheduleAllForType($actionType);
                    if ($count > 0) {
                        $this->dispatch('notify', message: "Updated {$count} pending reminder(s).");
                    }
                } else {
                    $count = $scheduler->cancelAllForType($actionType);
                    if ($count > 0) {
                        $this->dispatch('notify', message: "Cancelled {$count} pending reminder(s).");
                    }
                }
            }
        }

        session()->flash('success', 'Notification settings saved.');
    }

    public function render()
    {
        return view('livewire.pages.notification-settings.index');
    }
}
