<div>
    <h1 class="text-2xl font-bold mb-4">Add Baby Action</h1>

    <x-mary-card>
        <x-mary-form wire:submit="save">
            <x-mary-select label="Baby" wire:model="baby_id" :options="$babies" placeholder="Select a baby" required />
            <x-mary-select label="Action Type" wire:model.live="baby_action_type_id" :options="$actionTypes" placeholder="Select action type" required />

            @if ($this->isEatAction)
                <x-mary-select
                    label="Food Type"
                    wire:model.live="food_type"
                    :options="$foodTypes"
                    placeholder="Select food type (optional)"
                />

                @if ($food_type === \App\Enums\FoodType::BreastMilk->value)
                    <x-mary-select
                        label="Breast Side"
                        wire:model="breast_side"
                        :options="$breastSides"
                        placeholder="Select breast side (optional)"
                    />
                @endif
            @endif

            <x-mary-input label="Started At" wire:model="started_at" type="datetime-local" required />
            <x-mary-input label="Finished At" wire:model="finished_at" type="datetime-local" />

            <x-slot:actions>
                <x-mary-button label="Cancel" link="{{ route('baby_actions.show') }}" class="btn-ghost" />
                <x-mary-button type="submit" label="Save" class="btn-primary" />
            </x-slot:actions>
        </x-mary-form>
    </x-mary-card>
</div>

@script
<script>
    const now = new Date();
    now.setSeconds(0, 0);
    $wire.set('started_at', new Date(now - now.getTimezoneOffset() * 60000).toISOString().slice(0, 16));
</script>
@endscript
