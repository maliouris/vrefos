<?php

namespace App\Observers;

use App\Models\BabyAction;
use App\Services\LocalNotificationScheduler;

final class BabyActionObserver
{
    public function __construct(private LocalNotificationScheduler $scheduler) {}

    public function created(BabyAction $action): void
    {
        $this->scheduler->scheduleFor($action);
    }

    public function updated(BabyAction $action): void
    {
        if ($action->wasChanged(['started_at', 'finished_at', 'baby_action_type_id'])) {
            $this->scheduler->rescheduleFor($action);
        }
    }

    public function deleted(BabyAction $action): void
    {
        $this->scheduler->cancelFor($action);
    }
}
