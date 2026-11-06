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

    <x-mary-card>
        <x-mary-form wire:submit="update">
            <x-mary-input label="Name" wire:model="name" required />
            <x-mary-datepicker label="Birth Date" wire:model="birth_date" icon="o-calendar" />

            <x-slot:actions>
                <x-mary-button label="Cancel" link="{{ route('babies.show') }}" class="btn-ghost" />
                <x-mary-button type="submit" label="Save" spinner="update" wire:loading.attr="disabled" wire:target="update" class="btn-primary" />
            </x-slot:actions>
        </x-mary-form>
    </x-mary-card>

    <x-confirm-delete
        :show="$showDeleteModal"
        title="Delete {{ $baby->name }}?"
        message="This will delete {{ $baby->name }} and all of their recorded actions, and cancel any scheduled reminders. This cannot be undone."
        confirm="confirmDelete"
    />
</div>
