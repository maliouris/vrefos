<div>
    <h1 class="text-2xl font-bold mb-4">Edit Baby</h1>

    @if (session('success'))
        <x-alert title="{{ session('success') }}" class="alert-success mb-4" />
    @endif

    <x-card>
        <x-form wire:submit="update">
            <x-input label="Name" wire:model="name" required />
            <x-datepicker label="Birth Date" wire:model="birth_date" icon="o-calendar" />

            <x-slot:actions>
                <x-button label="Back" link="{{ route('babies.show') }}" class="btn-ghost" />
                <x-button type="submit" label="Update" class="btn-primary" />
            </x-slot:actions>
        </x-form>
    </x-card>
</div>
