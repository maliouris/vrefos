@php
    use Carbon\CarbonInterface;

    $typeIcon = \App\Models\BabyActionType::iconFor(...);

    $humanDiff = fn ($date): string => $date->diffForHumans([
        'parts' => 2,
        'short' => true,
        'syntax' => CarbonInterface::DIFF_ABSOLUTE,
    ]);
@endphp

<div wire:poll.60s>
    <div class="mb-5" x-data="{ hour: new Date().getHours() }">
        <p class="text-base text-base-content/60" x-text="hour < 5 ? 'Still up? You are doing great ☾' : hour < 12 ? 'Good morning ☀︎' : hour < 18 ? 'Good afternoon' : 'Good evening ☾'">Hello</p>
        <h1 class="text-3xl font-extrabold">Today</h1>
    </div>

    <x-finished-undo :show="$lastFinishedId !== null" />

    <x-notification-permission-banner :status="$permissionStatus" />

    @if (!$hasBabies)
        <x-mary-card class="text-center">
            <div class="text-5xl mb-2">🍼</div>
            <h2 class="text-xl font-bold mb-1">No little ones yet</h2>
            <p class="text-base-content/70 mb-4">Add your baby to start tracking feeds, naps and reminders.</p>
            <x-mary-button label="Add my baby" icon="o-plus" link="{{ route('babies.create') }}" class="btn-primary btn-lg w-full" />
        </x-mary-card>
    @else
        <div class="grid gap-4 sm:grid-cols-2">
            @foreach ($cards as $card)
                @php
                    $baby = $card['baby'];
                    $avatarColors = ['eat', 'sleep', 'temperature', 'medication', 'other'];
                    $avatar = $avatarColors[$baby->id % count($avatarColors)];
                    $ongoing = $card['actions']->first(fn ($a) => $a->finished_at === null && ! $a->babyActionType?->is_instant);
                @endphp
                <x-mary-card class="min-w-0" wire:key="baby-card-{{ $baby->id }}">
                    <div class="flex items-center gap-3 mb-4">
                        <span class="type-chip-{{ $avatar }} flex items-center justify-center w-12 h-12 rounded-full text-xl font-extrabold shrink-0">{{ mb_strtoupper(mb_substr($baby->name, 0, 1)) }}</span>
                        <h2 class="text-xl font-bold truncate flex-1">{{ $baby->name }}</h2>
                        @if ($card['age'])
                            <span class="badge badge-ghost shrink-0">{{ $card['age'] }}</span>
                        @endif
                    </div>

                    {{-- NOW STRIP --}}
                    @if ($ongoing)
                        @php($slug = $ongoing->babyActionType->slug())
                        <div class="type-chip-{{ $slug }} rounded-2xl p-4 mb-4">
                            <div class="flex items-center gap-2 text-base-content/70">
                                <x-mary-icon name="{{ $typeIcon($ongoing->babyActionType->name) }}" class="type-icon shrink-0" />
                                <span class="font-semibold">{{ $ongoing->babyActionType->name }} · now</span>
                            </div>
                            <div class="text-4xl font-extrabold tabular-nums my-1"
                                 x-data="{
                                     start: new Date(@js($ongoing->started_at->copy()->utc()->format('Y-m-d\TH:i:s\Z'))).getTime(),
                                     now: Date.now(),
                                     timer: null,
                                     get label() {
                                         const t = Math.max(0, Math.floor((this.now - this.start) / 1000));
                                         const h = Math.floor(t / 3600), m = Math.floor((t % 3600) / 60), s = t % 60;
                                         const pad = (n) => String(n).padStart(2, '0');
                                         return (h ? h + ':' + pad(m) : m) + ':' + pad(s);
                                     },
                                 }"
                                 x-init="timer = setInterval(() => now = Date.now(), 1000)"
                                 x-on:livewire:navigating.window="clearInterval(timer)"
                                 x-effect="$el.textContent = label"
                                 role="timer" aria-label="Elapsed time">{{ $humanDiff($ongoing->started_at) }}</div>
                            <x-mary-button label="Stop" icon="o-stop" class="btn-primary btn-lg w-full mt-2" wire:click="finishNow({{ $ongoing->id }})" />
                        </div>
                    @endif

                    {{-- QUICK START --}}
                    @if ($card['quickTypes']->isNotEmpty())
                        <div class="grid grid-cols-2 gap-2 mb-4">
                            @foreach ($card['quickTypes'] as $type)
                                <a href="{{ route('baby_actions.create', ['baby' => $baby->id, 'type' => $type->id]) }}" wire:navigate
                                   class="type-chip-{{ $type->slug() }} flex flex-col items-center justify-center gap-1 min-h-20 rounded-2xl font-bold active:scale-95 transition-transform">
                                    <x-mary-icon name="{{ $typeIcon($type->name) }}" class="type-icon w-7 h-7" />
                                    <span>{{ $type->name }}</span>
                                </a>
                            @endforeach
                        </div>
                    @endif

                    {{-- LATEST ACTIONS --}}
                    <div class="overflow-x-auto">
                        @forelse ($card['actions'] as $action)
                            <div class="flex items-center gap-3 mb-2" wire:key="action-{{ $action->id }}">
                                <span class="type-chip-{{ $action->babyActionType?->slug() ?? 'other' }} flex items-center justify-center w-9 h-9 rounded-full shrink-0">
                                    <x-mary-icon name="{{ $typeIcon($action->babyActionType?->name) }}" class="type-icon w-5 h-5" />
                                </span>
                                <span class="whitespace-nowrap">
                                    <span class="font-semibold">{{ $action->babyActionType?->name }}</span>
                                    @if ($action->finished_at === null)
                                        <span class="text-base-content/70">· {{ $humanDiff($action->started_at) }}</span>
                                    @else
                                        <span class="text-base-content/70"
                                              x-data="{
                                                  end: new Date(@js($action->finished_at->copy()->utc()->format('Y-m-d\TH:i:s\Z'))).getTime(),
                                                  now: Date.now(),
                                                  timer: null,
                                                  get label() {
                                                      const m = Math.max(0, Math.floor((this.now - this.end) / 60000));
                                                      if (m < 1) return 'just now';
                                                      const d = Math.floor(m / 1440), h = Math.floor((m % 1440) / 60), mm = m % 60;
                                                      const parts = d ? [d + 'd', h ? h + 'h' : ''] : h ? [h + 'h', mm ? mm + 'm' : ''] : [mm + 'm'];
                                                      return parts.filter(Boolean).join(' ') + ' ago';
                                                  },
                                              }"
                                              x-init="timer = setInterval(() => now = Date.now(), 15000)"
                                              x-on:livewire:navigating.window="clearInterval(timer)"
                                              x-effect="$el.textContent = '· ended ' + label">· ended {{ $humanDiff($action->finished_at) }} ago</span>
                                    @endif
                                </span>
                            </div>
                        @empty
                            <div class="text-base-content/60 text-center py-2">Nothing logged yet. Tap a tile to start 💛</div>
                        @endforelse
                    </div>
                </x-mary-card>
            @endforeach
        </div>
    @endif
</div>
