<div>
    <div class="flex justify-between items-center mb-4">
        <h1 class="text-2xl font-bold">Babies</h1>
        <x-mary-button label="Add Baby" icon="o-plus" link="{{ route('babies.create') }}" class="btn-primary" />
    </div>

    @if (session('success'))
        <x-mary-alert title="{{ session('success') }}" class="alert-success mb-4" />
    @endif

    @php
        $headers = [
            ['key' => 'name', 'label' => 'Name'],
            ['key' => 'birth_date', 'label' => 'Birth Date', 'format' => ['date', 'd/m/Y']],
        ];
    @endphp

    <x-mary-table :headers="$headers" :rows="$babies">
        @scope('actions', $baby)
            <div class="flex items-center gap-2">
                <x-mary-button label="Edit" icon="o-pencil" link="{{ route('babies.edit', $baby) }}" class="btn-ghost btn-sm" />
                <x-mary-button
                    label="Delete"
                    icon="o-trash"
                    class="btn-ghost btn-sm text-error"
                    wire:click="delete({{ $baby->id }})"
                    wire:confirm="Delete {{ $baby->name }} and all of their recorded actions? This cannot be undone."
                />
            </div>
        @endscope
    </x-mary-table>
</div>
