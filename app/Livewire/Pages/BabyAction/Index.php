<?php

namespace App\Livewire\Pages\BabyAction;

use App\Livewire\Concerns\FinishesActions;
use App\Models\Baby;
use App\Models\BabyAction;
use App\Models\BabyActionType;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    use FinishesActions;

    private const PAGE_SIZE = 20;

    public ?int $babyFilter = null;

    public ?int $typeFilter = null;

    public int $limit = self::PAGE_SIZE;

    public function filterByBaby(?int $babyId): void
    {
        $this->babyFilter = $this->babyFilter === $babyId ? null : $babyId;
        $this->limit = self::PAGE_SIZE;
    }

    public function filterByType(?int $typeId): void
    {
        $this->typeFilter = $this->typeFilter === $typeId ? null : $typeId;
        $this->limit = self::PAGE_SIZE;
    }

    public function loadMore(): void
    {
        $this->limit += self::PAGE_SIZE;
    }

    public function render()
    {
        $hasBabies = Baby::exists();

        // One extra row tells us whether a further page exists without a second count query.
        $rows = BabyAction::with(['baby', 'babyActionType', 'eatDetail', 'temperatureDetail', 'medicationDetail.medication'])
            ->when($this->babyFilter, fn ($query, $babyId) => $query->where('baby_id', $babyId))
            ->when($this->typeFilter, fn ($query, $typeId) => $query->where('baby_action_type_id', $typeId))
            ->orderByDesc('started_at')
            ->limit($this->limit + 1)
            ->get();

        $hasMore = $rows->count() > $this->limit;
        $babyActions = $rows->take($this->limit);

        return view('livewire.pages.baby-action.index', [
            'babyActions' => $babyActions,
            'hasBabies' => $hasBabies,
            'hasMore' => $hasMore,
            'babies' => Baby::orderBy('name')->get(['id', 'name']),
            'actionTypes' => BabyActionType::orderBy('id')->get(['id', 'name']),
        ]);
    }
}
