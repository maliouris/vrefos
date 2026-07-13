<div>
    <div class="flex justify-between items-center mb-4">
        <h1 class="text-2xl font-bold">Edit Baby</h1>
        <x-mary-button
            label="Delete"
            icon="o-trash"
            class="btn-error btn-outline btn-sm"
            wire:click="promptDelete"
        />
    </div>

    @if (session('success'))
        <x-mary-alert title="{{ session('success') }}" class="alert-success mb-4" />
    @endif

    <x-mary-card>
        <x-mary-form wire:submit="update">
            <x-mary-input label="Name" wire:model="name" required />
            <x-mary-datepicker label="Birth Date" wire:model="birth_date" icon="o-calendar" />

            <x-slot:actions>
                <x-mary-button label="Back" link="{{ route('babies.show') }}" class="btn-ghost" />
                <x-mary-button type="submit" label="Update" class="btn-primary" />
            </x-slot:actions>
        </x-mary-form>
    </x-mary-card>

    {{-- Delete Modal --}}
    @if ($showDeleteModal)
        <div class="fixed inset-0 bg-black/50 z-40 flex items-center justify-center" @click.self="$wire.set('showDeleteModal', false)">
            <div class="bg-base-100 rounded-lg p-6 max-w-md w-full mx-4 shadow-lg">
                <h3 class="text-lg font-bold mb-4">Delete {{ $baby->name }}?</h3>

                <p class="mb-4">This will delete {{ $baby->name }} and all of their recorded actions, and cancel any scheduled reminders. This cannot be undone.</p>

                <div class="flex gap-2">
                    <button class="btn btn-ghost flex-1" wire:click="$set('showDeleteModal', false)">Cancel</button>
                    <button class="btn btn-error flex-1" wire:click="confirmDelete">Delete</button>
                </div>
            </div>
        </div>
    @endif
</div>
