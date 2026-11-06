<?php

namespace App\Observers;

use App\Models\BabyAction;
use App\Services\PlanScheduler;

final class BabyActionObserver
{
    public function __construct(private PlanScheduler $scheduler) {}

    public function created(BabyAction $action): void
    {
        $this->scheduler->reanchorForAction($action);
    }

    public function updated(BabyAction $action): void
    {
        if ($action->wasChanged(['started_at', 'finished_at', 'baby_action_type_id'])) {
            $this->scheduler->reanchorForAction($action);

            if ($action->wasChanged('baby_action_type_id')) {
                $original = (clone $action)->forceFill([
                    'baby_action_type_id' => $action->getOriginal('baby_action_type_id'),
                ]);
                $this->scheduler->reanchorForAction($original);
            }
        }
    }

    public function deleted(BabyAction $action): void
    {
        $this->scheduler->reanchorForAction($action);
    }
}
