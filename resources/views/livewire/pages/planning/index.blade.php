@php
    $calendarTokens = ['primary', 'secondary', 'accent', 'info', 'success', 'warning', 'error'];
    $calendars = [
        'planned' => [
            'colorName' => 'planned',
            'lightColors' => [
                'main' => 'var(--color-base-content)',
                'container' => 'var(--color-base-200)',
                'onContainer' => 'color-mix(in oklab, var(--color-base-content) 70%, var(--color-base-100))',
            ],
        ],
    ];
    $calendars['planned']['darkColors'] = $calendars['planned']['lightColors'];
    foreach ($actionTypes as $i => $type) {
        $token = $calendarTokens[$i % count($calendarTokens)];
        $colors = [
            'main' => "var(--color-{$token})",
            'container' => "color-mix(in oklab, var(--color-{$token}) 20%, var(--color-base-100))",
            'onContainer' => 'var(--color-base-content)',
        ];
        $calendars['type-'.$type->id] = [
            'colorName' => 'type-'.$type->id,
            'lightColors' => $colors,
            'darkColors' => $colors,
        ];
    }
@endphp

<div x-data x-init="$wire.setDeviceTimezone(Intl.DateTimeFormat().resolvedOptions().timeZone)">
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-2xl font-bold">Planning</h1>
        <x-mary-button label="Add plan" icon="o-plus" wire:click="openCreate" class="btn-primary btn-sm" />
    </div>

    <x-notification-permission-banner :status="$permissionStatus" />

    @if ($babies->count() > 1)
        <div class="mb-4 flex flex-wrap gap-2">
            <x-mary-button
                label="All children"
                wire:click="showAllBabies"
                aria-pressed="{{ $activeBabyIds === [] ? 'true' : 'false' }}" class="btn-sm {{ $activeBabyIds === [] ? 'btn-primary' : 'btn-outline' }}"
            />
            @foreach ($babies as $baby)
                <x-mary-button
                    label="{{ $baby->name }}"
                    wire:click="toggleActiveBaby({{ $baby->id }})"
                    aria-pressed="{{ in_array($baby->id, $activeBabyIds) ? 'true' : 'false' }}" class="btn-sm {{ in_array($baby->id, $activeBabyIds) ? 'btn-primary' : 'btn-outline' }}"
                />
            @endforeach
        </div>
    @endif

    <div
        id="planning-calendar"
        wire:ignore
        x-data
        x-init="window.initPlanningCalendar($wire, $el)"
        data-calendars="{{ json_encode($calendars) }}"
        class="mb-8 rounded-lg overflow-hidden border border-base-300"
        style="height: min(720px, calc(100dvh - 10rem))"
    ></div>

    <div class="space-y-4">
        <h3 class="text-lg font-semibold">Manage plans</h3>

        @forelse ($plans as $plan)
            <x-mary-card
                class="shadow-sm cursor-pointer active:bg-base-200 transition-colors"
                wire:key="plan-{{ $plan->id }}"
                role="button"
                tabindex="0"
                wire:click="openEdit({{ $plan->id }})"
                wire:keydown.enter.self="openEdit({{ $plan->id }})"
            >
                <div class="flex items-center gap-3 mb-3">
                    <div @click.stop>
                        <x-mary-toggle wire:click="toggleEnabled({{ $plan->id }})" :checked="$plan->enabled" aria-label="Enable {{ $plan->babyActionType->name }} plan" />
                    </div>
                    <span class="text-lg font-semibold truncate">{{ $plan->babyActionType->name }}</span>
                    @if ($plan->ends_on && $plan->ends_on->isPast())
                        <span class="badge badge-ghost">Ended</span>
                    @endif
                    @if ($plan->notifications->isEmpty())
                        <span class="badge badge-warning badge-outline">No reminders</span>
                    @endif
                </div>

                <div class="text-base text-base-content/70 space-y-1.5">
                    @php($version = $plan->currentVersion())
                    @if ($version)
                        <div>
                            Every {{ rtrim(rtrim(number_format($version->intervalValue(), 1), '0'), '.') }}
                            {{ $version->interval_unit->value }}
                        </div>
                    @endif
                    <div>
                        {{ $plan->all_children ? 'All children' : $plan->babies->pluck('name')->join(', ') }}
                    </div>
                    @if ($plan->starts_on || $plan->ends_on)
                        <div>
                            {{ $plan->starts_on?->format('j M Y') ?? 'Anytime' }}
                            &ndash;
                            {{ $plan->ends_on?->format('j M Y') ?? 'Open-ended' }}
                        </div>
                    @endif
                </div>

                <x-mary-icon name="o-chevron-right" class="absolute bottom-5 right-5 text-base-content/30" />
            </x-mary-card>
        @empty
            <p class="text-sm opacity-70">No plans yet.</p>
        @endforelse
    </div>

    <style>
        .event-modal .modal-box > form > button.absolute {
            display: none;
        }
    </style>

    {{-- Details sheet for a tapped calendar event: bottom sheet on phones,
         centered dialog from `sm` up. Nothing navigates until a button is pressed. --}}
    <x-mary-modal wire:model="showEventModal" class="event-modal modal-bottom sm:modal-middle">
        @if ($selectedEvent !== [])
            <h3 class="text-xl font-extrabold">{{ $selectedEvent['title'] }}</h3>
            <p class="mt-1 text-base-content/70">{{ $selectedEvent['when'] }}</p>

            <ul class="mt-4 space-y-1">
                @foreach ($selectedEvent['lines'] as $line)
                    <li>{{ $line }}</li>
                @endforeach
            </ul>

            <div class="mt-6 flex flex-wrap gap-3">
                <x-mary-button label="Close" class="btn-ghost flex-1" wire:click="$set('showEventModal', false)" />
                @if ($selectedEvent['kind'] === 'action')
                    <a href="{{ $selectedEvent['url'] }}" wire:navigate class="btn btn-primary flex-1">Open action</a>
                @else
                    <x-mary-button label="Edit plan" class="btn-outline flex-1" wire:click="openPlanFromEvent" />
                    <a href="{{ $selectedEvent['url'] }}" wire:navigate class="btn btn-primary flex-1">Log now</a>
                @endif
            </div>
        @endif
    </x-mary-modal>

    <style>
        .plan-modal .modal-box > form > button.absolute {
            display: none;
        }
    </style>

    {{-- persistent: a stray backdrop tap or Escape must not throw away a half-filled plan; Cancel/Save close it. --}}
    <x-mary-modal wire:model="showModal" class="plan-modal" persistent>
        <div class="flex items-center justify-between gap-3 mb-5">
            <h3 class="text-xl font-extrabold">{{ $editingId ? 'Edit plan' : 'New plan' }}</h3>
            @if ($editingId)
                <x-mary-button
                    icon="o-trash"
                    aria-label="Delete plan"
                    class="btn-circle btn-sm btn-ghost text-error"
                    wire:click="promptDeletePlan({{ $editingId }})"
                />
            @endif
        </div>

        <x-mary-form wire:submit="savePlan">
            <div class="flex flex-col gap-4">
                <div>
                    <label class="label"><span class="label-text">Action type</span></label>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($actionTypes as $type)
                            <x-mary-button
                                label="{{ $type->name }}"
                                wire:click="$set('babyActionTypeId', {{ $type->id }})"
                                aria-pressed="{{ $babyActionTypeId === $type->id ? 'true' : 'false' }}" class="btn-sm {{ $babyActionTypeId === $type->id ? 'btn-primary' : 'btn-outline' }}"
                            />
                        @endforeach
                    </div>
                    @error('babyActionTypeId') <span class="text-error text-sm">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="label"><span class="label-text">Repeat</span></label>
                    <div class="flex flex-wrap gap-2 mb-3">
                        @foreach (\App\Enums\IntervalUnit::cases() as $unit)
                            <x-mary-button
                                label="{{ ucfirst($unit->value) }}"
                                wire:click="toggleUnit('{{ $unit->value }}')"
                                aria-pressed="{{ $intervalUnit === $unit->value ? 'true' : 'false' }}" class="btn-sm {{ $intervalUnit === $unit->value ? 'btn-primary' : 'btn-outline' }}"
                            />
                        @endforeach
                    </div>
                    <x-mary-input
                        label="Every"
                        wire:model="intervalValue"
                        type="number"
                        step="0.5"
                        min="0.5"
                        hint="e.g. every 3 {{ $intervalUnit }}"
                    />
                </div>

                @if ($intervalUnit === 'hours')
                    <x-mary-select
                        label="Count from"
                        wire:model="notifyFrom"
                        :options="[
                            ['id' => 'started_at', 'name' => 'Last start time'],
                            ['id' => 'finished_at', 'name' => 'Last end time'],
                        ]"
                        option-value="id"
                        option-label="name"
                    />
                @endif

                @if (in_array($intervalUnit, ['days', 'weeks', 'months']))
                    <div>
                        <label class="label"><span class="label-text">Times of day</span></label>
                        <div class="flex flex-col gap-2">
                            @foreach ($times as $i => $time)
                                <div class="flex items-center gap-2">
                                    <x-mary-input type="time" wire:model="times.{{ $i }}" class="flex-1" />
                                    @if (count($times) > 1)
                                        <x-mary-button icon="o-x-mark" aria-label="Remove time" class="btn-sm btn-ghost" wire:click="removeTimeEntry({{ $i }})" />
                                    @endif
                                </div>
                            @endforeach
                            <x-mary-button label="Add time" icon="o-plus" class="btn-sm btn-outline w-fit" wire:click="addTimeEntry" />
                        </div>
                    </div>
                @endif

                @if ($intervalUnit === 'weeks')
                    <div>
                        <label class="label"><span class="label-text">Specific days (optional)</span></label>
                        <div class="flex flex-wrap gap-2">
                            @foreach (['Mon' => 1, 'Tue' => 2, 'Wed' => 3, 'Thu' => 4, 'Fri' => 5, 'Sat' => 6, 'Sun' => 7] as $label => $iso)
                                <x-mary-button
                                    label="{{ $label }}"
                                    wire:click="toggleWeekday({{ $iso }})"
                                    aria-pressed="{{ in_array($iso, $weekdays) ? 'true' : 'false' }}" class="btn-sm {{ in_array($iso, $weekdays) ? 'btn-primary' : 'btn-outline' }}"
                                />
                            @endforeach
                        </div>
                    </div>
                @endif

                @if (in_array($intervalUnit, ['days', 'weeks', 'months']))
                    <x-mary-input label="Cadence start date" wire:model="anchorDate" type="date" />
                @endif

                <x-mary-input
                    label="Duration (minutes, optional)"
                    wire:model="durationMinutes"
                    type="number"
                    min="1"
                    hint="Needed to attach an end-of-slot notification"
                />

                <div class="flex gap-3">
                    <x-mary-input label="Starts on (optional)" wire:model="startsOn" type="date" class="flex-1" />
                    <x-mary-input label="Ends on (optional)" wire:model="endsOn" type="date" class="flex-1" />
                </div>
                @error('endsOn') <span class="text-error text-sm">{{ $message }}</span> @enderror

                <div>
                    <label class="label"><span class="label-text">Children</span></label>
                    <div class="flex flex-wrap gap-2">
                        <x-mary-button
                            label="All children"
                            wire:click="toggleAllChildren"
                            aria-pressed="{{ $allChildren ? 'true' : 'false' }}" class="btn-sm {{ $allChildren ? 'btn-primary' : 'btn-outline' }}"
                        />
                        @foreach ($babies as $baby)
                            <x-mary-button
                                label="{{ $baby->name }}"
                                wire:click="toggleTargetBaby({{ $baby->id }})"
                                aria-pressed="{{ in_array($baby->id, $targetBabyIds) ? 'true' : 'false' }}" class="btn-sm {{ in_array($baby->id, $targetBabyIds) ? 'btn-primary' : 'btn-outline' }}"
                            />
                        @endforeach
                    </div>
                    @error('targetBabyIds') <span class="text-error text-sm">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="label"><span class="label-text">Notify at start</span></label>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($notifications as $notification)
                            <x-mary-button
                                label="{{ $notification->title }}"
                                wire:click="toggleStartNotification({{ $notification->id }})"
                                aria-pressed="{{ in_array($notification->id, $startNotificationIds) ? 'true' : 'false' }}" class="btn-sm {{ in_array($notification->id, $startNotificationIds) ? 'btn-primary' : 'btn-outline' }}"
                            />
                        @endforeach
                    </div>
                    <div class="flex gap-2 mt-2">
                        <x-mary-input wire:model="newStartMessageTitle" placeholder="New message title" class="flex-1" />
                        <x-mary-button label="Add" wire:click="createAndAttachMessage('start')" class="btn-sm" />
                    </div>
                </div>

                <div>
                    <label class="label"><span class="label-text">Notify at end</span></label>
                    @if (blank($durationMinutes))
                        <p class="text-sm opacity-70">Set a duration above to enable end notifications.</p>
                    @else
                        <div class="flex flex-wrap gap-2">
                            @foreach ($notifications as $notification)
                                <x-mary-button
                                    label="{{ $notification->title }}"
                                    wire:click="toggleEndNotification({{ $notification->id }})"
                                    aria-pressed="{{ in_array($notification->id, $endNotificationIds) ? 'true' : 'false' }}" class="btn-sm {{ in_array($notification->id, $endNotificationIds) ? 'btn-primary' : 'btn-outline' }}"
                                />
                            @endforeach
                        </div>
                        <div class="flex gap-2 mt-2">
                            <x-mary-input wire:model="newEndMessageTitle" placeholder="New message title" class="flex-1" />
                            <x-mary-button label="Add" wire:click="createAndAttachMessage('end')" class="btn-sm" />
                        </div>
                    @endif
                </div>

                @if ($startNotificationIds === [] && $endNotificationIds === [])
                    <x-mary-alert
                        icon="o-bell-slash"
                        title="No reminders"
                        description="This plan only shows on the calendar. Attach a message above to get a notification."
                        class="alert-warning"
                    />
                @endif

                <x-mary-toggle label="Enabled" wire:model="enabled" />
            </div>

            <x-slot:actions>
                <x-mary-button label="Cancel" wire:click="$set('showModal', false)" />
                <x-mary-button label="Save" type="submit" spinner="savePlan" wire:loading.attr="disabled" wire:target="savePlan" class="btn-primary" />
            </x-slot:actions>
        </x-mary-form>
    </x-mary-modal>

    <x-confirm-delete
        :show="$showDeleteModal"
        title="Delete plan?"
        message="This will delete the plan and cancel its scheduled reminders."
        confirm="deletePlan"
        cancel="closeDeleteModal"
    />
</div>
