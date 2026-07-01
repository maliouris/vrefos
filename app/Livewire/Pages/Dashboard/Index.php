<?php

namespace App\Livewire\Pages\Dashboard;

use App\Models\Baby;
use App\Models\BabyAction;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
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
            'age' => $this->ageLabel($baby->birth_date),
            'actions' => $baby->babyActions,
        ]);

        return view('livewire.pages.dashboard.index', [
            'cards' => $cards,
            'hasBabies' => $babies->isNotEmpty(),
        ]);
    }

    private function ageLabel(?Carbon $birthDate): ?string
    {
        if ($birthDate === null) {
            return null;
        }

        $months = $birthDate->diffInMonths(now());

        if ($months < 1) {
            $days = (int) $birthDate->diffInDays(now());

            return $days <= 0 ? 'newborn' : $days.'d';
        }

        if ($months < 24) {
            return ((int) $months).' mo';
        }

        $years = (int) $birthDate->diffInYears(now());
        $remainingMonths = ((int) $months) - ($years * 12);

        return $remainingMonths > 0 ? $years.'y '.$remainingMonths.'mo' : $years.'y';
    }
}
