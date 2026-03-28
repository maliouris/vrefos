<div>
    <h1 class="text-2xl font-bold mb-4">Edit Baby Action</h1>

    @if (session('success'))
        <x-alert title="{{ session('success') }}" class="alert-success mb-4" />
    @endif

    <x-card>
        <x-form wire:submit="update">
            <x-select label="Baby" wire:model="baby_id" :options="$babies" placeholder="Select a baby" required />
            <x-select label="Action Type" wire:model="baby_action_type_id" :options="$actionTypes" placeholder="Select action type" required />
            <x-input label="Started At" wire:model="started_at" type="datetime-local" required />
            <x-input label="Finished At" wire:model="finished_at" type="datetime-local" />

            <x-slot:actions>
                <x-button label="Back" link="{{ route('baby_actions.show') }}" class="btn-ghost" />
                <x-button type="submit" label="Update" class="btn-primary" />
            </x-slot:actions>
        </x-form>
    </x-card>
</div>
