<?php

namespace Database\Factories;

use App\Models\Baby;
use App\Models\BabyAction;
use App\Models\BabyActionType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BabyAction>
 */
class BabyActionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'baby_id' => Baby::factory(),
            'baby_action_type_id' => BabyActionType::query()->inRandomOrder()->first()?->id ?? 1,
            'started_at' => fake()->dateTimeBetween('-7 days', 'now'),
            'finished_at' => fake()->optional(0.7)->dateTimeBetween('-6 days', 'now'),
        ];
    }
}
