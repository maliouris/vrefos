<div>
    <h1 class="text-2xl font-bold mb-4">Add Baby</h1>

    <x-mary-card>
        <x-mary-form wire:submit="save">
            <x-mary-input label="Name" wire:model="name" required />
            <x-mary-datepicker label="Birth Date" wire:model="birth_date" icon="o-calendar" />

            <x-slot:actions>
                <x-mary-button label="Cancel" link="{{ route('babies.show') }}" class="btn-ghost" />
                <x-mary-button type="submit" label="Save" class="btn-primary" />
            </x-slot:actions>
        </x-mary-form>
    </x-mary-card>
</div>
