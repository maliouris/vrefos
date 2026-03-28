<div>
    <h1 class="text-2xl font-bold mb-4">Add Baby</h1>

    <x-card>
        <x-form wire:submit="save">
            <x-input label="Name" wire:model="name" required />
            <x-datepicker label="Birth Date" wire:model="birth_date" icon="o-calendar" />

            <x-slot:actions>
                <x-button label="Cancel" link="{{ route('babies.show') }}" class="btn-ghost" />
                <x-button type="submit" label="Save" class="btn-primary" />
            </x-slot:actions>
        </x-form>
    </x-card>
</div>
