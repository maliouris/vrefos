<?php

namespace Database\Factories;

use App\Enums\IntervalUnit;
use App\Enums\NotifyFrom;
use App\Models\Plan;
use App\Models\PlanVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlanVersion>
 */
class PlanVersionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'plan_id' => Plan::factory(),
            'effective_from' => now()->toDateString(),
            'interval_minutes' => 180,
            'interval_unit' => IntervalUnit::Hours,
            'times' => null,
            'weekdays' => null,
            'anchor_date' => now()->toDateString(),
            'notify_from' => NotifyFrom::StartedAt,
            'duration_minutes' => null,
        ];
    }

    public function daily(array $times = ['08:00']): static
    {
        return $this->state(fn (array $attributes) => [
            'interval_minutes' => 1440,
            'interval_unit' => IntervalUnit::Days,
            'times' => $times,
            'notify_from' => null,
        ]);
    }

    public function weekly(array $weekdays = [1, 3, 5], array $times = ['08:00']): static
    {
        return $this->state(fn (array $attributes) => [
            'interval_minutes' => 10080,
            'interval_unit' => IntervalUnit::Weeks,
            'times' => $times,
            'weekdays' => $weekdays,
            'notify_from' => null,
        ]);
    }
}
