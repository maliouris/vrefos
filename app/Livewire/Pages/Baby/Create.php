<?php

namespace App\Livewire\Pages\Baby;

use App\Models\Baby;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Create extends Component
{
    public string $name = '';
    public string $birth_date = '';

    public function save(): void
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'birth_date' => 'required|date',
        ]);

        auth()->user()->babies()->create([
            'name' => $this->name,
            'birth_date' => $this->birth_date,
        ]);

        session()->flash('success', 'Baby created successfully.');

        $this->redirect(route('babies.show'), navigate: true);
    }

    public function render()
    {
        return view('livewire.pages.baby.create');
    }
}
