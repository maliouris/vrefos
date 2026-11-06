<div>
    <div class="flex justify-between items-center mb-4">
        <h1 class="text-2xl font-bold">Baby Actions</h1>
        @if ($hasBabies)
            <x-mary-button label="Add Action" icon="o-plus" link="{{ route('baby_actions.create') }}" class="btn-primary" />
        @endif
    </div>

    <x-finished-undo :show="$lastFinishedId !== null" />

    @if (!$hasBabies)
        <x-mary-alert title="No children added yet" description="You need to add a child before you can record baby actions." class="alert-info mb-4">
            <x-slot:actions>
                <x-mary-button label="Add a child" icon="o-plus" link="{{ route('babies.create') }}" class="btn-primary btn-sm" />
            </x-slot:actions>
        </x-mary-alert>
    @else
        @php
            $typeIcon = \App\Models\BabyActionType::iconFor(...);

            $totalTime = fn ($action): string => $action->finished_at->diffForHumans($action->started_at, [
                'parts' => 2,
                'short' => true,
                'syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE,
            ]);
        @endphp

        {{-- FILTERS --}}
        <div class="mb-4 space-y-2" role="group" aria-label="Filter actions">
            @if ($babies->count() > 1)
                <div class="flex flex-wrap gap-2">
                    @foreach ($babies as $baby)
                        <x-mary-button
                            label="{{ $baby->name }}"
                            wire:click="filterByBaby({{ $baby->id }})"
                            aria-pressed="{{ $babyFilter === $baby->id ? 'true' : 'false' }}"
                            class="btn-sm {{ $babyFilter === $baby->id ? 'btn-primary' : 'btn-outline' }}"
                        />
                    @endforeach
                </div>
            @endif
            <div class="flex flex-wrap gap-2">
                @foreach ($actionTypes as $type)
                    <x-mary-button
                        label="{{ $type->name }}"
                        icon="{{ $type->icon() }}"
                        wire:click="filterByType({{ $type->id }})"
                        aria-pressed="{{ $typeFilter === $type->id ? 'true' : 'false' }}"
                        class="btn-sm {{ $typeFilter === $type->id ? 'btn-primary' : 'btn-outline' }}"
                    />
                @endforeach
            </div>
        </div>

        <div class="space-y-4">
            @forelse ($babyActions as $action)
                <x-mary-card
                    class="shadow-sm cursor-pointer active:bg-base-200 transition-colors"
                    wire:key="action-{{ $action->id }}"
                    x-data
                    role="link"
                    tabindex="0"
                    @click="Livewire.navigate('{{ route('baby_actions.edit', $action) }}')"
                    @keydown.enter.self="Livewire.navigate('{{ route('baby_actions.edit', $action) }}')"
                >
                    <div class="flex items-center justify-between gap-2 mb-3">
                        <div class="flex items-center gap-2 min-w-0">
                            <x-mary-icon name="{{ $typeIcon($action->babyActionType?->name) }}" class="shrink-0 {{ ! $action->babyActionType?->is_instant && $action->finished_at === null ? 'text-primary' : 'text-base-content/40' }}" />
                            <span class="text-lg font-semibold truncate">{{ $action->babyActionType?->name }}</span>
                            <span class="text-base-content/70 truncate">· {{ $action->baby?->name }}</span>
                        </div>
                        @if ($action->eatDetail?->food_type)
                            <span class="badge badge-info shrink-0">{{ $action->eatDetail->food_type->label() }}</span>
                        @elseif ($action->temperatureDetail)
                            <span class="badge {{ $action->temperatureDetail->feverLevel()->badgeClass() }} shrink-0">{{ $action->temperatureDetail->temperature }}°C · {{ $action->temperatureDetail->feverLevel()->label() }}</span>
                        @elseif ($action->medicationDetail)
                            <span class="badge badge-info shrink-0">{{ $action->medicationDetail->medication->name }}{{ $action->medicationDetail->amount_ml ? ' · ' . $action->medicationDetail->amount_ml . ' ml' : '' }}</span>
                        @endif
                    </div>

                    <div class="text-base text-base-content/70 space-y-1.5">
                        @if ($action->babyActionType?->is_instant)
                            <div>At: <span x-data x-text="window.formatLocalDateTime(@js(optional($action->started_at)->format('Y-m-d H:i')))"></span></div>
                        @else
                            <div>Started: <span x-data x-text="window.formatLocalDateTime(@js(optional($action->started_at)->format('Y-m-d H:i')))"></span></div>
                            @if ($action->finished_at)
                                <div>Finished: <span x-data x-text="window.formatLocalDateTime(@js($action->finished_at->format('Y-m-d H:i')))"></span></div>
                                <div>Total: {{ $totalTime($action) }}</div>
                            @endif
                        @endif
                    </div>

                    @unless ($action->babyActionType?->is_instant || $action->finished_at)
                        <div class="w-fit mt-4" @click.stop>
                            <x-mary-button
                                label="Finish now"
                                icon="o-flag"
                                class="btn-primary btn-sm"
                                wire:click="finishNow({{ $action->id }})"
                            />
                        </div>
                    @endunless

                    <x-mary-icon name="o-chevron-right" class="absolute bottom-5 right-5 text-base-content/30" />
                </x-mary-card>
            @empty
                <div class="flex items-center gap-2 text-base-content/70">
                    <x-mary-icon name="o-minus-circle" class="shrink-0" />
                    <span>{{ $babyFilter || $typeFilter ? 'No actions match these filters' : 'No actions recorded yet' }}</span>
                </div>
            @endforelse
        </div>

        @if ($hasMore)
            <div class="mt-4 flex justify-center">
                <x-mary-button label="Load more" icon="o-arrow-down" wire:click="loadMore" spinner="loadMore" class="btn-outline" />
            </div>
        @endif
    @endif
</div>
