<div>
    <h1 class="text-2xl font-bold mb-4">Add Baby Action</h1>

    <x-mary-card>
        <x-mary-form wire:submit="save">
            <x-mary-select label="Baby" wire:model="baby_id" :options="$babies" placeholder="Select a baby" required />
            <x-mary-select label="Action Type" wire:model="baby_action_type_id" :options="$actionTypes" placeholder="Select action type" required />
            <x-mary-input label="Started At" wire:model="started_at" type="datetime-local" required />
            <x-mary-input label="Finished At" wire:model="finished_at" type="datetime-local" />

            <x-slot:actions>
                <x-mary-button label="Cancel" link="{{ route('baby_actions.show') }}" class="btn-ghost" />
                <x-mary-button type="submit" label="Save" class="btn-primary" />
            </x-slot:actions>
        </x-mary-form>
    </x-mary-card>
</div>
