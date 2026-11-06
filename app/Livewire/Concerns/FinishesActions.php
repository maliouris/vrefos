<?php

namespace App\Livewire\Concerns;

use App\Models\BabyAction;

/**
 * One-tap "Finish now" with an undo, replacing the old confirm dialog.
 */
trait FinishesActions
{
    public ?int $lastFinishedId = null;

    public function finishNow(BabyAction $babyAction): void
    {
        if ($babyAction->babyActionType?->is_instant || $babyAction->finished_at !== null) {
            return;
        }

        $babyAction->update(['finished_at' => now()]);

        $this->lastFinishedId = $babyAction->id;
    }

    public function undoFinish(): void
    {
        if ($this->lastFinishedId !== null) {
            BabyAction::whereKey($this->lastFinishedId)->update(['finished_at' => null]);
        }

        $this->lastFinishedId = null;
    }

    public function dismissFinished(): void
    {
        $this->lastFinishedId = null;
    }
}
