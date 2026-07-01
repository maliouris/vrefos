@php
    use Carbon\CarbonInterface;

    $typeIcon = fn (?string $name): string => match (strtolower((string) $name)) {
        'eat' => 'o-cake',
        'sleep' => 'o-moon',
        default => 'o-clock',
    };

    $humanDiff = fn ($date): string => $date->diffForHumans([
        'parts' => 2,
        'short' => true,
        'syntax' => CarbonInterface::DIFF_ABSOLUTE,
    ]);
@endphp

<div wire:poll.60s>
    <div class="flex justify-between items-center mb-4">
        <h1 class="text-2xl font-bold">Dashboard</h1>
    </div>

    @if (session('success'))
        <x-mary-alert title="{{ session('success') }}" class="alert-success mb-4" />
    @endif

    {{-- QUICK ACTIONS --}}
    <div class="grid grid-cols-1 sm:flex sm:flex-wrap gap-2 mb-6">
        @if ($hasBabies)
            <x-mary-button label="New Action" icon="o-plus" link="{{ route('baby_actions.create') }}" class="btn-primary" />
        @endif
        <x-mary-button label="Add Child" icon="o-cake" link="{{ route('babies.create') }}" class="{{ $hasBabies ? 'btn-outline' : 'btn-primary' }}" />
    </div>

    @if (!$hasBabies)
        <x-mary-alert title="No children added yet" description="Add a child to start tracking activities and reminders." class="alert-info" icon="o-information-circle">
            <x-slot:actions>
                <x-mary-button label="Add a child" icon="o-plus" link="{{ route('babies.create') }}" class="btn-primary btn-sm" />
            </x-slot:actions>
        </x-mary-alert>
    @else
        <div class="grid gap-4 sm:grid-cols-2">
            @foreach ($cards as $card)
                @php($baby = $card['baby'])
                <x-mary-card class="shadow-sm min-w-0" wire:key="baby-card-{{ $baby->id }}">
                    <div class="flex items-center justify-between mb-3">
                        <h2 class="text-lg font-semibold">{{ $baby->name }}</h2>
                        @if ($card['age'])
                            <span class="badge badge-ghost">{{ $card['age'] }}</span>
                        @endif
                    </div>

                    {{-- LATEST ACTIONS --}}
                    <div class="overflow-x-auto">
                        @forelse ($card['actions'] as $action)
                            <div class="flex items-center justify-between gap-2 mb-2" wire:key="action-{{ $action->id }}">
                                <div class="flex items-center gap-2">
                                    <x-mary-icon name="{{ $typeIcon($action->babyActionType?->name) }}" class="shrink-0 {{ $action->finished_at === null ? 'text-primary' : 'text-base-content/40' }}" />
                                    <span class="whitespace-nowrap">
                                        <span class="font-medium">{{ $action->babyActionType?->name }}</span>
                                        @if ($action->finished_at === null)
                                            <span class="text-base-content/60">· {{ $humanDiff($action->started_at) }}</span>
                                        @else
                                            <span class="text-base-content/60">· ended {{ $humanDiff($action->finished_at) }} ago</span>
                                        @endif
                                    </span>
                                </div>
                                @if ($action->finished_at === null)
                                    <x-mary-button
                                        label="Finish now"
                                        icon="o-flag"
                                        class="btn-ghost btn-xs shrink-0"
                                        wire:click="finishNow({{ $action->id }})"
                                        wire:confirm="Mark this action as finished now?"
                                    />
                                @endif
                            </div>
                        @empty
                            <div class="flex items-center gap-2 mb-2 text-base-content/60">
                                <x-mary-icon name="o-minus-circle" class="shrink-0" />
                                <span>No actions yet</span>
                            </div>
                        @endforelse
                    </div>
                </x-mary-card>
            @endforeach
        </div>
    @endif
</div>
