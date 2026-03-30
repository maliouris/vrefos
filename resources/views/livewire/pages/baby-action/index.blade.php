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
                ['key' => 'started_at',           'label' => 'Started At',  'format' => ['date', 'd/m/Y H:i']],
                ['key' => 'finished_at',          'label' => 'Finished At', 'format' => ['date', 'd/m/Y H:i']],
                ['key' => 'eatDetail.food_type',  'label' => 'Food Type'],
            ];
        @endphp

        <x-mary-table :headers="$headers" :rows="$babyActions">
            @scope('cell_eatDetail.food_type', $action)
                @if ($action->eatDetail?->food_type)
                    {{ $action->eatDetail->food_type->label() }}{{ $action->eatDetail->breast_side ? ' - ' . $action->eatDetail->breast_side->label() : '' }}
                @else
                    —
                @endif
            @endscope

            @scope('actions', $action)
                <x-mary-button label="Edit" icon="o-pencil" link="{{ route('baby_actions.edit', $action) }}" class="btn-ghost btn-sm" />
            @endscope
        </x-mary-table>
    @endif
</div>
