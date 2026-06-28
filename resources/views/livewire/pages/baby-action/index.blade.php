<div>
    <div class="flex justify-between items-center mb-4">
        <h1 class="text-2xl font-bold">Baby Actions</h1>
        @if ($hasBabies)
            <x-mary-button label="Add Action" icon="o-plus" link="{{ route('baby_actions.create') }}" class="btn-primary" />
        @endif
    </div>

    @if (session('success'))
        <x-mary-alert title="{{ session('success') }}" class="alert-success mb-4" />
    @endif

    @if (!$hasBabies)
        <x-mary-alert title="No children added yet" description="You need to add a child before you can record baby actions." class="alert-info mb-4">
            <x-slot:actions>
                <x-mary-button label="Add a child" icon="o-plus" link="{{ route('babies.create') }}" class="btn-primary btn-sm" />
            </x-slot:actions>
        </x-mary-alert>
    @else
        @php
            $headers = [
                ['key' => 'baby.name',           'label' => 'Baby'],
                ['key' => 'babyActionType.name',  'label' => 'Type'],
                ['key' => 'started_at',           'label' => 'Started At'],
                ['key' => 'finished_at',          'label' => 'Finished At'],
                ['key' => 'eatDetail.food_type',  'label' => 'Food Type'],
            ];
        @endphp

        <x-mary-table :headers="$headers" :rows="$babyActions">
            @scope('cell_started_at', $action)
                <span x-data x-text="window.formatLocalDateTime(@js(optional($action->started_at)->format('Y-m-d H:i')))"></span>
            @endscope

            @scope('cell_finished_at', $action)
                <span x-data x-text="window.formatLocalDateTime(@js(optional($action->finished_at)->format('Y-m-d H:i')))"></span>
            @endscope

            @scope('cell_eatDetail.food_type', $action)
                @if ($action->eatDetail?->food_type)
                    {{ $action->eatDetail->food_type->label() }}{{ $action->eatDetail->breast_side ? ' - ' . $action->eatDetail->breast_side->label() : '' }}
                @else
                    —
                @endif
            @endscope

            @scope('actions', $action)
                <div class="flex items-center gap-2">
                    @unless ($action->finished_at)
                        <x-mary-button
                            label="Finish now"
                            icon="o-flag"
                            class="btn-ghost btn-sm"
                            wire:click="finishNow({{ $action->id }})"
                            wire:confirm="Mark this action as finished now?"
                        />
                    @endunless
                    <x-mary-button label="Edit" icon="o-pencil" link="{{ route('baby_actions.edit', $action) }}" class="btn-ghost btn-sm" />
                </div>
            @endscope
        </x-mary-table>
    @endif
</div>
