<?php

namespace App\Livewire\Pages\BabyAction;

use App\Models\Baby;
use App\Models\BabyAction;
use App\Models\BabyActionType;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Create extends Component
{
    public ?int $baby_id = null;
    public ?int $baby_action_type_id = null;
    public string $started_at = '';
    public string $finished_at = '';

    public function mount(): void
    {
        $this->started_at = now()->format('Y-m-d\TH:i');
        $this->finished_at = now()->format('Y-m-d\TH:i');
    }

    public function save(): void
    {
        $this->validate([
            'baby_id' => 'required|exists:babies,id',
            'baby_action_type_id' => 'required|exists:baby_action_types,id',
            'started_at' => 'required|date',
            'finished_at' => 'nullable|date|after_or_equal:started_at',
        ]);

        BabyAction::create([
            'baby_id' => $this->baby_id,
            'baby_action_type_id' => $this->baby_action_type_id,
            'started_at' => $this->started_at,
            'finished_at' => $this->finished_at ?: null,
        ]);

        session()->flash('success', 'Baby action created successfully.');

        $this->redirect(route('baby_actions.show'), navigate: true);
    }

    public function render()
    {
        $babies = auth()->user()->babies()->get()->map(fn ($b) => ['id' => $b->id, 'name' => $b->name]);
        $actionTypes = BabyActionType::all()->map(fn ($t) => ['id' => $t->id, 'name' => $t->name]);

        return view('livewire.pages.baby-action.create', compact('babies', 'actionTypes'));
    }
}
