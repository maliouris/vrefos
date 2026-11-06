<?php

namespace App\Livewire\Pages\Notification;

use App\Livewire\Concerns\HandlesNotificationPermission;
use App\Models\Notification;
use App\Services\PlanScheduler;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    use HandlesNotificationPermission;

    public bool $showModal = false;

    public ?int $editingId = null;

    public string $title = '';

    public ?string $description = null;

    public bool $showDeleteModal = false;

    public ?int $deletingId = null;

    public function openCreate(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function openEdit(int $notificationId): void
    {
        $notification = Notification::findOrFail($notificationId);

        $this->editingId = $notification->id;
        $this->title = $notification->title;
        $this->description = $notification->description;
        $this->showModal = true;
    }

    public function save(PlanScheduler $scheduler): void
    {
        $this->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:255',
        ]);

        if ($this->editingId !== null) {
            $notification = Notification::findOrFail($this->editingId);
            $notification->update([
                'title' => $this->title,
                'description' => $this->description,
            ]);

            foreach ($notification->plans as $plan) {
                $scheduler->rescheduleFor($plan);
            }
        } else {
            Notification::create([
                'title' => $this->title,
                'description' => $this->description,
            ]);
        }

        $this->showModal = false;
        $this->resetForm();

        session()->flash('success', 'Notification message saved.');
    }

    public function promptDelete(int $notificationId): void
    {
        $this->showModal = false;
        $this->deletingId = $notificationId;
        $this->showDeleteModal = true;
    }

    public function closeDeleteModal(): void
    {
        $this->showDeleteModal = false;
        $this->deletingId = null;
    }

    public function confirmDelete(PlanScheduler $scheduler): void
    {
        $notification = Notification::with('plans')->findOrFail($this->deletingId);
        $affectedPlans = $notification->plans;

        $notification->delete();

        foreach ($affectedPlans as $plan) {
            $scheduler->rescheduleFor($plan);
        }

        $this->closeDeleteModal();
        $this->resetForm();

        session()->flash('success', 'Notification message deleted.');
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->title = '';
        $this->description = null;
    }

    public function render()
    {
        $notifications = Notification::orderBy('title')->get();

        return view('livewire.pages.notification.index', compact('notifications'));
    }
}
