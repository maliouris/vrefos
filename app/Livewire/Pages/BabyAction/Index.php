<?php

namespace App\Livewire\Pages\BabyAction;

use App\Livewire\Concerns\FinishesActions;
use App\Models\Baby;
use App\Models\BabyAction;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    use FinishesActions;

    public function render()
    {
        $hasBabies = Baby::exists();

        $babyActions = BabyAction::with(['baby', 'babyActionType', 'eatDetail', 'temperatureDetail', 'medicationDetail.medication'])
            ->orderByDesc('started_at')
            ->get();

        return view('livewire.pages.baby-action.index', compact('babyActions', 'hasBabies'));
    }
}
