<?php

namespace App\Observers;

use App\Models\Baby;
use App\Models\Plan;
use App\Services\PlanScheduler;

final class BabyObserver
{
    public function __construct(private PlanScheduler $scheduler) {}

    public function created(Baby $baby): void
    {
        $allChildrenPlans = Plan::where('all_children', true)->get();

        $baby->plans()->syncWithoutDetaching($allChildrenPlans->pluck('id'));

        foreach ($allChildrenPlans as $plan) {
            $this->scheduler->rescheduleFor($plan);
        }
    }
}
