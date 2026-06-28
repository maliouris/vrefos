<?php

namespace Database\Factories;

use App\Enums\NotifyFrom;
use App\Models\BabyActionType;
use App\Models\NotificationSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationSetting>
 */
class NotificationSettingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'baby_action_type_id' => BabyActionType::query()->inRandomOrder()->first()?->id ?? 1,
            'enabled' => true,
            'notify_after_minutes' => 180,
            'notify_from' => NotifyFrom::StartedAt,
            'title' => 'Time to check on your baby!',
        ];
    }
}
