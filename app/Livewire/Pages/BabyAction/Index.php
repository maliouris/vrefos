<?php

namespace App\Livewire\Pages\BabyAction;

use App\Models\Baby;
use App\Models\BabyAction;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    public function finishNow(BabyAction $babyAction): void
    {
        if ($babyAction->babyActionType?->is_instant || $babyAction->finished_at !== null) {
            return;
        }

        $babyAction->update(['finished_at' => now()]);

        session()->flash('success', 'Baby action finished.');
    }

    public function render()
    {
        $hasBabies = Baby::exists();

        $babyActions = BabyAction::with(['baby', 'babyActionType', 'eatDetail', 'temperatureDetail', 'medicationDetail.medication'])
            ->orderByDesc('started_at')
            ->get();

        return view('livewire.pages.baby-action.index', compact('babyActions', 'hasBabies'));
    }
}
