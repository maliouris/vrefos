<?php

namespace Database\Factories;

use App\Models\BabyActionType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BabyActionType>
 */
class BabyActionTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->word(),
            'is_instant' => false,
        ];
    }

    public function instant(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_instant' => true,
        ]);
    }
}
