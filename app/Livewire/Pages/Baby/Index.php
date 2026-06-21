<?php

namespace App\Livewire\Pages\Baby;

use App\Models\Baby;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    public function mount(): void {}

    public function render()
    {
        $babies = Baby::all();

        return view('livewire.pages.baby.index', compact('babies'));
    }
}
