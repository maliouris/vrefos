<?php

namespace App\Livewire\Pages\NotificationSettings;

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
        $user = auth()->user();
        $actionTypes = BabyActionType::all();

        foreach ($actionTypes as $actionType) {
            $setting = NotificationSetting::firstOrCreate(
                ['user_id' => $user->id, 'baby_action_type_id' => $actionType->id],
                ['enabled' => true, 'notify_after_minutes' => 180]
            );

            $this->settings[$actionType->id] = [
                'name' => $actionType->name,
                'enabled' => $setting->enabled,
                'notify_after_minutes' => $setting->notify_after_minutes,
            ];
        }
    }

    public function save(): void
    {
        $user = auth()->user();

        foreach ($this->settings as $actionTypeId => $data) {
            $this->validate([
                "settings.{$actionTypeId}.notify_after_minutes" => 'required|integer|min:1|max:10080',
            ]);

            NotificationSetting::where('user_id', $user->id)
                ->where('baby_action_type_id', $actionTypeId)
                ->update([
                    'enabled' => $data['enabled'],
                    'notify_after_minutes' => $data['notify_after_minutes'],
                ]);
        }

        session()->flash('success', 'Notification settings saved.');
    }

    public function render()
    {
        return view('livewire.pages.notification-settings.index');
    }
}
