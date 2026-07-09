<?php

namespace App\Livewire\Pages\Baby;

use App\Models\Baby;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    public function mount(): void {}

    /**
     * Delete the baby's actions one by one through Eloquent (so
     * BabyActionObserver cancels their scheduled notifications) before
     * removing the baby — the DB-level cascade would skip the observer.
     */
    public function delete(Baby $baby): void
    {
        foreach ($baby->babyActions()->get() as $action) {
            $action->delete();
        }

        $baby->delete();

        session()->flash('success', 'Baby deleted.');
    }

    public function render()
    {
        $babies = Baby::all();

        return view('livewire.pages.baby.index', compact('babies'));
    }
}
