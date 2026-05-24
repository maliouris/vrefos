<?php

namespace Database\Factories;

use App\Enums\FoodType;
use App\Models\BabyAction;
use App\Models\BabyActionEatDetail;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BabyActionEatDetail>
 */
class BabyActionEatDetailFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'baby_action_id' => BabyAction::factory(),
            'food_type' => fake()->randomElement(FoodType::cases())->value,
            'breast_side' => null,
        ];
    }
}
