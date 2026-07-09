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
            $typeIcon = fn (?string $name): string => match (strtolower((string) $name)) {
                'eat' => 'o-cake',
                'sleep' => 'o-moon',
                default => 'o-clock',
            };

            $totalTime = fn ($action): string => $action->finished_at->diffForHumans($action->started_at, [
                'parts' => 2,
                'short' => true,
                'syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE,
            ]);
        @endphp

        <div class="space-y-4">
            @forelse ($babyActions as $action)
                <x-mary-card
                    class="shadow-sm cursor-pointer active:bg-base-200 transition-colors"
                    wire:key="action-{{ $action->id }}"
                    x-data
                    @click="Livewire.navigate('{{ route('baby_actions.edit', $action) }}')"
                >
                    @if ($action->finished_at === null)
                        <span class="badge badge-primary badge-outline bg-base-100 absolute top-0 left-1/2 -translate-x-1/2 -translate-y-1/2">Ongoing</span>
                    @endif

                    <div class="flex items-center gap-2">
                        <div class="grow min-w-0">
                            <div class="flex items-center justify-between gap-2 mb-3">
                                <div class="flex items-center gap-2 min-w-0">
                                    <x-mary-icon name="{{ $typeIcon($action->babyActionType?->name) }}" class="shrink-0 {{ $action->finished_at === null ? 'text-primary' : 'text-base-content/40' }}" />
                                    <span class="font-semibold truncate">{{ $action->babyActionType?->name }}</span>
                                    <span class="text-base-content/60 truncate">· {{ $action->baby?->name }}</span>
                                </div>
                                @if ($action->eatDetail?->food_type)
                                    <span class="badge badge-info shrink-0">{{ $action->eatDetail->food_type->label() }}{{ $action->eatDetail->breast_side ? ' - ' . $action->eatDetail->breast_side->label() : '' }}</span>
                                @endif
                            </div>

                            <div class="text-sm text-base-content/60 space-y-1.5">
                                <div>Started: <span x-data x-text="window.formatLocalDateTime(@js(optional($action->started_at)->format('Y-m-d H:i')))"></span></div>
                                @if ($action->finished_at)
                                    <div>Finished: <span x-data x-text="window.formatLocalDateTime(@js($action->finished_at->format('Y-m-d H:i')))"></span></div>
                                    <div>Total: {{ $totalTime($action) }}</div>
                                @endif
                            </div>

                            @unless ($action->finished_at)
                                <div class="w-fit mt-4" @click.stop>
                                    <x-mary-button
                                        label="Finish now"
                                        icon="o-flag"
                                        class="btn-primary btn-sm"
                                        wire:click="finishNow({{ $action->id }})"
                                        wire:confirm="Mark this action as finished now?"
                                    />
                                </div>
                            @endunless
                        </div>

                        <x-mary-icon name="o-chevron-right" class="shrink-0 self-center text-base-content/30" />
                    </div>
                </x-mary-card>
            @empty
                <div class="flex items-center gap-2 text-base-content/60">
                    <x-mary-icon name="o-minus-circle" class="shrink-0" />
                    <span>No actions recorded yet</span>
                </div>
            @endforelse
        </div>
    @endif
</div>
