<?php

namespace App\Livewire\Pages\BabyAction;

use App\Models\BabyAction;
use App\Models\BabyActionType;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Edit extends Component
{
    public BabyAction $babyAction;

    public ?int $baby_id = null;

    public ?int $baby_action_type_id = null;

    public string $started_at = '';

    public string $finished_at = '';

    public function mount(BabyAction $babyAction): void
    {
        $this->authorize('update', $babyAction);
        $this->babyAction = $babyAction;
        $this->baby_id = $babyAction->baby_id;
        $this->baby_action_type_id = $babyAction->baby_action_type_id;
        $this->started_at = $babyAction->started_at?->format('Y-m-d\TH:i') ?? '';
        $this->finished_at = $babyAction->finished_at?->format('Y-m-d\TH:i') ?? '';
    }

    public function update(): void
    {
        $this->validate([
            'baby_id' => 'required|exists:babies,id',
            'baby_action_type_id' => 'required|exists:baby_action_types,id',
            'started_at' => 'required|date',
            'finished_at' => 'nullable|date|after_or_equal:started_at',
        ]);

        $this->babyAction->update([
            'baby_id' => $this->baby_id,
            'baby_action_type_id' => $this->baby_action_type_id,
            'started_at' => $this->started_at,
            'finished_at' => $this->finished_at ?: null,
        ]);

        session()->flash('success', 'Baby action updated successfully.');
    }

    public function render()
    {
        $babies = auth()->user()->babies()->get()->map(fn ($b) => ['id' => $b->id, 'name' => $b->name]);
        $actionTypes = BabyActionType::all()->map(fn ($t) => ['id' => $t->id, 'name' => $t->name]);

        return view('livewire.pages.baby-action.edit', compact('babies', 'actionTypes'));
    }
}
