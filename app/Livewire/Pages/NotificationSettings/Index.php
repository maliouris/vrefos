<?php

namespace App\Livewire\Pages\NotificationSettings;

use App\Enums\NotifyFrom;
use App\Models\BabyActionType;
use App\Models\NotificationSetting;
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

    public function save(): void
    {
        foreach ($this->settings as $actionTypeId => $data) {
            $this->validate([
                "settings.{$actionTypeId}.notify_after_minutes" => 'required|integer|min:1|max:10080',
                "settings.{$actionTypeId}.notify_from" => 'required|in:started_at,finished_at',
            ]);

            NotificationSetting::where('baby_action_type_id', $actionTypeId)
                ->update([
                    'enabled' => $data['enabled'],
                    'notify_after_minutes' => $data['notify_after_minutes'],
                    'notify_from' => $data['notify_from'],
                ]);
        }

        session()->flash('success', 'Notification settings saved.');
    }

    public function render()
    {
        return view('livewire.pages.notification-settings.index');
    }
}
