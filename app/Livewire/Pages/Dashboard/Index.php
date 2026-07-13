<?php

namespace App\Livewire\Pages\Dashboard;

use App\Livewire\Concerns\HandlesNotificationPermission;
use App\Models\Baby;
use App\Models\BabyAction;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    use HandlesNotificationPermission;

    public function finishNow(BabyAction $babyAction): void
    {
        if ($babyAction->finished_at !== null) {
            return;
        }

        $babyAction->update(['finished_at' => now()]);

        session()->flash('success', 'Baby action finished.');
    }

    public function render()
    {
        $babies = Baby::with(['babyActions' => function ($query) {
            $query->with('babyActionType')
                ->orderByDesc('started_at')
                ->limit(3);
        }])->orderBy('name')->get();

        $cards = $babies->map(fn (Baby $baby): array => [
            'baby' => $baby,
            'age' => $baby->ageLabel(),
            'actions' => $baby->babyActions,
        ]);

        return view('livewire.pages.dashboard.index', [
            'cards' => $cards,
            'hasBabies' => $babies->isNotEmpty(),
        ]);
    }
}
