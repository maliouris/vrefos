<?php

namespace Database\Factories;

use App\Models\BabyActionType;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'baby_action_type_id' => BabyActionType::query()->inRandomOrder()->first()?->id ?? BabyActionType::factory(),
            'starts_on' => null,
            'ends_on' => null,
            'device_timezone' => 'UTC',
            'all_children' => true,
            'enabled' => true,
        ];
    }
}
