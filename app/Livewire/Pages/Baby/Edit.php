<?php

namespace App\Livewire\Pages\Baby;

use App\Models\Baby;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Mary\Traits\Toast;

#[Layout('layouts.app')]
class Edit extends Component
{
    use Toast;

    public Baby $baby;

    public string $name = '';

    public string $birth_date = '';

    public bool $showDeleteModal = false;

    public function mount(Baby $baby): void
    {
        $this->baby = $baby;
        $this->name = $baby->name;
        $this->birth_date = $baby->birth_date?->format('Y-m-d') ?? '';
    }

    public function update(): void
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'birth_date' => 'nullable|date',
        ]);

        $this->baby->update([
            'name' => $this->name,
            'birth_date' => $this->birth_date ?: null,
        ]);

        $this->success('Baby updated successfully.');
    }

    public function promptDelete(): void
    {
        $this->showDeleteModal = true;
    }

    /**
     * Delete the baby's actions one by one through Eloquent (so
     * BabyActionObserver cancels their scheduled notifications) before
     * removing the baby — the DB-level cascade would skip the observer.
     */
    public function confirmDelete(): void
    {
        foreach ($this->baby->babyActions()->get() as $action) {
            $action->delete();
        }

        $this->baby->delete();

        $this->success('Baby deleted.');

        $this->redirectRoute('babies.show', navigate: true);
    }

    public function render()
    {
        return view('livewire.pages.baby.edit');
    }
}
