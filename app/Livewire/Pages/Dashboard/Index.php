<?php

namespace App\Livewire\Pages\Dashboard;

use App\Livewire\Concerns\FinishesActions;
use App\Livewire\Concerns\HandlesNotificationPermission;
use App\Models\Baby;
use App\Models\BabyAction;
use App\Models\BabyActionType;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    use FinishesActions;
    use HandlesNotificationPermission;

    public function render()
    {
        $babies = Baby::with(['babyActions' => function ($query) {
            $query->with('babyActionType')
                ->orderByDesc('started_at')
                ->limit(3);
        }])->orderBy('name')->get();

        $actionTypes = BabyActionType::orderBy('id')->get();

        // Types with a running (non-instant) action per baby: those show Stop instead of a start button.
        $runningTypeIds = BabyAction::whereNull('finished_at')
            ->whereHas('babyActionType', fn ($query) => $query->where('is_instant', false))
            ->get(['baby_id', 'baby_action_type_id'])
            ->groupBy('baby_id')
            ->map(fn ($actions) => $actions->pluck('baby_action_type_id')->all());

        $cards = $babies->map(fn (Baby $baby): array => [
            'baby' => $baby,
            'age' => $baby->ageLabel(),
            'actions' => $baby->babyActions,
            'quickTypes' => $actionTypes->reject(fn (BabyActionType $type) => in_array($type->id, $runningTypeIds->get($baby->id, []), true)),
        ]);

        return view('livewire.pages.dashboard.index', [
            'cards' => $cards,
            'hasBabies' => $babies->isNotEmpty(),
        ]);
    }
}
