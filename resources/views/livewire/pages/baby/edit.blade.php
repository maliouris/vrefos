<div>
    <h1 class="text-2xl font-bold mb-4">Edit Baby</h1>

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
</div>
