<div>
    <div class="flex justify-between items-center mb-4">
        <h1 class="text-2xl font-bold">Babies</h1>
        <x-mary-button label="Add Baby" icon="o-plus" link="{{ route('babies.create') }}" class="btn-primary" />
    </div>

    @if (session('success'))
        <x-mary-alert title="{{ session('success') }}" class="alert-success mb-4" />
    @endif

    <div class="space-y-4">
        @forelse ($babies as $baby)
            <x-mary-card
                class="shadow-sm cursor-pointer active:bg-base-200 transition-colors"
                wire:key="baby-{{ $baby->id }}"
                x-data
                @click="Livewire.navigate('{{ route('babies.edit', $baby) }}')"
            >
                <div class="flex items-center justify-between gap-2 mb-3">
                    <div class="flex items-center gap-2 min-w-0">
                        <span class="text-lg font-semibold truncate">{{ $baby->name }}</span>
                    </div>
                    @if ($baby->ageLabel())
                        <span class="badge badge-ghost shrink-0">{{ $baby->ageLabel() }}</span>
                    @endif
                </div>

                @if ($baby->birth_date)
                    <div class="text-base text-base-content/60">
                        Born: {{ $baby->birth_date->format('d/m/Y') }}
                    </div>
                @endif

                <x-mary-icon name="o-chevron-right" class="absolute bottom-5 right-5 text-base-content/30" />
            </x-mary-card>
        @empty
            <div class="flex items-center gap-2 text-base-content/60">
                <x-mary-icon name="o-minus-circle" class="shrink-0" />
                <span>No children added yet</span>
            </div>
        @endforelse
    </div>
</div>
