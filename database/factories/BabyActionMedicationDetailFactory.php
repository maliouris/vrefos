<?php

namespace Database\Factories;

use App\Models\BabyAction;
use App\Models\BabyActionMedicationDetail;
use App\Models\Medication;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BabyActionMedicationDetail>
 */
class BabyActionMedicationDetailFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'baby_action_id' => BabyAction::factory(),
            'medication_id' => Medication::factory(),
            'amount_ml' => null,
        ];
    }
}
