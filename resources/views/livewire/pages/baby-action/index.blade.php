<div>
    <div class="flex justify-between items-center mb-4">
        <h1 class="text-2xl font-bold">Baby Actions</h1>
        <x-button label="Add Action" icon="o-plus" link="{{ route('baby_actions.create') }}" class="btn-primary" />
    </div>

    @if (session('success'))
        <x-alert title="{{ session('success') }}" class="alert-success mb-4" />
    @endif

    @php
        $headers = [
            ['key' => 'baby.name', 'label' => 'Baby'],
            ['key' => 'babyActionType.name', 'label' => 'Type'],
            ['key' => 'started_at', 'label' => 'Started At', 'format' => ['date', 'd/m/Y H:i']],
            ['key' => 'finished_at', 'label' => 'Finished At', 'format' => ['date', 'd/m/Y H:i']],
        ];
    @endphp

    <x-table :headers="$headers" :rows="$babyActions">
        @scope('actions', $action)
            <x-button label="Edit" icon="o-pencil" link="{{ route('baby_actions.edit', $action) }}" class="btn-ghost btn-sm" />
        @endscope
    </x-table>
</div>
