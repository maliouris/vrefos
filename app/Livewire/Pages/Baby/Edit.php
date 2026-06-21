<?php

namespace App\Livewire\Pages\Baby;

use App\Models\Baby;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Edit extends Component
{
    public Baby $baby;

    public string $name = '';

    public string $birth_date = '';

    public function mount(Baby $baby): void
    {
        $this->baby = $baby;
        $this->name = $baby->name;
        $this->birth_date = $baby->birth_date->format('Y-m-d');
    }

    public function update(): void
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'birth_date' => 'required|date',
        ]);

        $this->baby->update([
            'name' => $this->name,
            'birth_date' => $this->birth_date,
        ]);

        session()->flash('success', 'Baby updated successfully.');
    }

    public function render()
    {
        return view('livewire.pages.baby.edit');
    }
}
