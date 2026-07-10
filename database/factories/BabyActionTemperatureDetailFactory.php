<?php

namespace Database\Factories;

use App\Models\BabyAction;
use App\Models\BabyActionTemperatureDetail;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BabyActionTemperatureDetail>
 */
class BabyActionTemperatureDetailFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'baby_action_id' => BabyAction::factory(),
            'temperature' => $this->faker->randomFloat(1, 35.5, 41.0),
        ];
    }
}
