<?php

namespace App\Livewire\Pages\Planning;

use App\Enums\FeverLevel;
use App\Enums\IntervalUnit;
use App\Enums\NotifyFrom;
use App\Livewire\Concerns\HandlesNotificationPermission;
use App\Models\Baby;
use App\Models\BabyAction;
use App\Models\BabyActionType;
use App\Models\Notification;
use App\Models\Plan;
use App\Models\PlanVersion;
use App\Services\PlanScheduler;
use App\Services\PlanTimeline;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    use HandlesNotificationPermission;

    public string $deviceTimezone = 'UTC';

    /** @var array<int, int> */
    public array $activeBabyIds = [];

    public bool $showModal = false;

    public ?int $editingId = null;

    // Plan-level fields
    public ?int $babyActionTypeId = null;

    public bool $allChildren = true;

    /** @var array<int, int> */
    public array $targetBabyIds = [];

    public bool $enabled = true;

    public ?string $startsOn = null;

    public ?string $endsOn = null;

    // Current-version (recurrence) fields
    public string $intervalUnit = 'hours';

    public float $intervalValue = 3;

    /** @var array<int, string> */
    public array $times = ['08:00'];

    /** @var array<int, int> */
    public array $weekdays = [];

    public string $anchorDate = '';

    public string $notifyFrom = 'started_at';

    public ?string $durationMinutes = null;

    // Notification attachments
    /** @var array<int, int> */
    public array $startNotificationIds = [];

    /** @var array<int, int> */
    public array $endNotificationIds = [];

    public string $newMessageTitle = '';

    public bool $showDeleteModal = false;

    public bool $showEventModal = false;

    /**
     * What the calendar's details sheet shows for the tapped event.
     *
     * @var array{kind?: string, title?: string, when?: string, lines?: array<int, string>, url?: string, planId?: int}
     */
    public array $selectedEvent = [];

    public ?int $deletingPlanId = null;

    public function mount(): void
    {
        $this->anchorDate = now()->toDateString();
    }

    public function setDeviceTimezone(string $timezone): void
    {
        if (! in_array($timezone, timezone_identifiers_list(), true)) {
            return;
        }

        $this->deviceTimezone = $timezone;

        $stalePlans = Plan::where('device_timezone', '!=', $timezone)->get();

        foreach ($stalePlans as $plan) {
            $plan->device_timezone = $timezone;
            $plan->save();
            app(PlanScheduler::class)->rescheduleFor($plan);
        }
    }

    public function toggleActiveBaby(int $babyId): void
    {
        if (in_array($babyId, $this->activeBabyIds, true)) {
            $this->activeBabyIds = array_values(array_diff($this->activeBabyIds, [$babyId]));
        } else {
            $this->activeBabyIds[] = $babyId;
        }

        $this->refreshCalendar();
    }

    public function showAllBabies(): void
    {
        $this->activeBabyIds = [];

        $this->refreshCalendar();
    }

    /**
     * The calendar is `wire:ignore` and fetches its own events, so it has to be
     * told whenever the data behind it (filter, plans) changes.
     */
    private function refreshCalendar(): void
    {
        $this->dispatch('planning-events-changed');
    }

    /**
     * Opens the details sheet for a calendar event. Tapping an event used to
     * navigate straight away, which left no way to read one hidden under another
     * (no hover on a phone) or to back out of a mis-tap.
     *
     * @param  string  $eventId  `action-{id}` or `plan-{planId}-{babyId}-{timestamp}` as built by getEvents()
     * @param  string  $start  ISO-8601 instant
     * @param  string|null  $end  ISO-8601 instant, only when the event has a real end time
     */
    public function showEvent(string $eventId, string $title, string $start, ?string $end = null): void
    {
        $tz = in_array($this->deviceTimezone, timezone_identifiers_list(), true) ? $this->deviceTimezone : 'UTC';

        try {
            $startAt = Carbon::parse($start)->timezone($tz);
            $endAt = $end !== null ? Carbon::parse($end)->timezone($tz) : null;
        } catch (\Throwable) {
            return;
        }

        $when = $startAt->format('D j M · H:i').($endAt ? ' – '.$endAt->format('H:i') : '');

        if (preg_match('/^action-(\d+)$/', $eventId, $matches) === 1) {
            $action = BabyAction::with(['baby', 'babyActionType', 'eatDetail', 'temperatureDetail', 'medicationDetail.medication'])->find((int) $matches[1]);

            if ($action === null) {
                return;
            }

            $lines = [$action->baby->name];

            if ($action->eatDetail?->food_type) {
                $lines[] = $action->eatDetail->food_type->label().($action->eatDetail->breast_side ? ' · '.$action->eatDetail->breast_side->label() : '');
            }

            if ($action->temperatureDetail) {
                $temperature = (float) $action->temperatureDetail->temperature;
                $lines[] = rtrim(rtrim(number_format($temperature, 1), '0'), '.').' °C · '.FeverLevel::fromTemperature($temperature)->label();
            }

            if ($action->medicationDetail) {
                $amount = $action->medicationDetail->amount_ml;
                $lines[] = $action->medicationDetail->medication->name.($amount !== null ? ' · '.rtrim(rtrim(number_format((float) $amount, 2), '0'), '.').' ml' : '');
            }

            if (! $action->babyActionType->is_instant && $action->finished_at === null) {
                $lines[] = 'Ongoing';
            }

            $this->selectedEvent = [
                'kind' => 'action',
                'title' => $title,
                'when' => $when,
                'lines' => $lines,
                'url' => route('baby_actions.edit', $action),
            ];
            $this->showEventModal = true;

            return;
        }

        if (preg_match('/^plan-(\d+)-(\d+)-\d+$/', $eventId, $matches) === 1) {
            $plan = Plan::with(['babyActionType', 'versions'])->find((int) $matches[1]);

            if ($plan === null) {
                return;
            }

            $lines = [match (true) {
                str_starts_with($title, '✗') => 'Missed',
                str_starts_with($title, '→') => 'Next up',
                default => 'Planned',
            }];

            if ($baby = Baby::find((int) $matches[2])) {
                $lines[] = $baby->name;
            }

            if ($version = $plan->currentVersion()) {
                $lines[] = 'Every '.rtrim(rtrim(number_format($version->intervalValue(), 1), '0'), '.').' '.$version->interval_unit->value;
            }

            $this->selectedEvent = [
                'kind' => 'plan',
                'title' => $title,
                'when' => $when,
                'lines' => $lines,
                'planId' => $plan->id,
            ];
            $this->showEventModal = true;
        }
    }

    public function updatedShowEventModal(bool $open): void
    {
        if (! $open) {
            $this->selectedEvent = [];
        }
    }

    public function openPlanFromEvent(): void
    {
        $planId = $this->selectedEvent['planId'] ?? null;

        $this->showEventModal = false;
        $this->selectedEvent = [];

        if ($planId !== null) {
            $this->openEdit((int) $planId);
        }
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function openEdit(int $planId): void
    {
        $plan = Plan::with(['versions', 'babies', 'startNotifications', 'endNotifications'])->findOrFail($planId);
        $version = $plan->currentVersion();

        $this->editingId = $plan->id;
        $this->babyActionTypeId = $plan->baby_action_type_id;
        $this->allChildren = $plan->all_children;
        $this->targetBabyIds = $plan->all_children ? [] : $plan->babies->pluck('id')->all();
        $this->enabled = $plan->enabled;
        $this->startsOn = $plan->starts_on?->toDateString();
        $this->endsOn = $plan->ends_on?->toDateString();

        if ($version !== null) {
            $this->intervalUnit = $version->interval_unit->value;
            $this->intervalValue = $version->intervalValue();
            $this->times = $version->times ?: ['08:00'];
            $this->weekdays = $version->weekdays ?: [];
            $this->anchorDate = $version->anchor_date->toDateString();
            $this->notifyFrom = ($version->notify_from ?? NotifyFrom::StartedAt)->value;
            $this->durationMinutes = $version->duration_minutes !== null ? (string) $version->duration_minutes : null;
        }

        $this->startNotificationIds = $plan->startNotifications->pluck('id')->all();
        $this->endNotificationIds = $plan->endNotifications->pluck('id')->all();

        $this->showModal = true;
    }

    public function toggleAllChildren(): void
    {
        $this->allChildren = true;
        $this->targetBabyIds = [];
    }

    public function toggleTargetBaby(int $babyId): void
    {
        if (in_array($babyId, $this->targetBabyIds, true)) {
            $this->targetBabyIds = array_values(array_diff($this->targetBabyIds, [$babyId]));
        } else {
            $this->targetBabyIds[] = $babyId;
        }

        $this->allChildren = $this->targetBabyIds === [];
    }

    public function toggleUnit(string $unit): void
    {
        $this->intervalUnit = $unit;

        if ($unit !== 'weeks') {
            $this->weekdays = [];
        }

        if (! in_array($unit, ['days', 'weeks', 'months'], true)) {
            $this->times = ['08:00'];
        }
    }

    public function toggleWeekday(int $weekday): void
    {
        if (in_array($weekday, $this->weekdays, true)) {
            $this->weekdays = array_values(array_diff($this->weekdays, [$weekday]));
        } else {
            $this->weekdays[] = $weekday;
        }
    }

    public function addTimeEntry(): void
    {
        $this->times[] = '08:00';
    }

    public function removeTimeEntry(int $index): void
    {
        unset($this->times[$index]);
        $this->times = array_values($this->times);

        if ($this->times === []) {
            $this->times = ['08:00'];
        }
    }

    public function toggleStartNotification(int $notificationId): void
    {
        if (in_array($notificationId, $this->startNotificationIds, true)) {
            $this->startNotificationIds = array_values(array_diff($this->startNotificationIds, [$notificationId]));
        } else {
            $this->startNotificationIds[] = $notificationId;
        }
    }

    public function toggleEndNotification(int $notificationId): void
    {
        if (in_array($notificationId, $this->endNotificationIds, true)) {
            $this->endNotificationIds = array_values(array_diff($this->endNotificationIds, [$notificationId]));
        } else {
            $this->endNotificationIds[] = $notificationId;
        }
    }

    public function createAndAttachMessage(string $trigger): void
    {
        $this->validate(['newMessageTitle' => 'required|string|max:255']);

        $notification = Notification::create(['title' => $this->newMessageTitle]);
        $this->newMessageTitle = '';

        if ($trigger === 'end') {
            $this->endNotificationIds[] = $notification->id;
        } else {
            $this->startNotificationIds[] = $notification->id;
        }
    }

    public function savePlan(PlanScheduler $scheduler): void
    {
        $this->validate([
            'babyActionTypeId' => 'required|exists:baby_action_types,id',
            'targetBabyIds' => [Rule::requiredIf(! $this->allChildren), 'array'],
            'targetBabyIds.*' => 'exists:babies,id',
            'startsOn' => 'nullable|date',
            'endsOn' => 'nullable|date|after_or_equal:startsOn',
            'intervalUnit' => ['required', Rule::enum(IntervalUnit::class)],
            'intervalValue' => 'required|numeric|min:0.1',
            'times' => 'array',
            'times.*' => 'date_format:H:i',
            'weekdays' => 'array',
            'weekdays.*' => 'integer|min:1|max:7',
            'anchorDate' => 'required|date',
            'notifyFrom' => ['required', Rule::enum(NotifyFrom::class)],
            'durationMinutes' => 'nullable|integer|min:1',
        ]);

        $planAttributes = [
            'baby_action_type_id' => $this->babyActionTypeId,
            'all_children' => $this->allChildren,
            'enabled' => $this->enabled,
            'starts_on' => $this->startsOn ?: null,
            'ends_on' => $this->endsOn ?: null,
            'device_timezone' => $this->deviceTimezone,
        ];

        if ($this->editingId !== null) {
            $plan = Plan::findOrFail($this->editingId);
            $plan->update($planAttributes);
        } else {
            $plan = Plan::create($planAttributes);
        }

        // A plain firstOrNew()/updateOrCreate() would miss same-day re-edits: the
        // `date` cast stores `effective_from` with a "00:00:00" time suffix, so an
        // exact-value WHERE never matches — whereDate() compares day-only instead.
        $version = PlanVersion::where('plan_id', $plan->id)
            ->whereDate('effective_from', now()->toDateString())
            ->first() ?? new PlanVersion([
                'plan_id' => $plan->id,
                'effective_from' => now()->toDateString(),
            ]);
        $version->setIntervalFromValue($this->intervalValue, IntervalUnit::from($this->intervalUnit));
        $version->times = in_array($this->intervalUnit, ['days', 'weeks', 'months'], true) ? array_values($this->times) : null;
        $version->weekdays = $this->intervalUnit === 'weeks' && $this->weekdays !== [] ? array_values($this->weekdays) : null;
        $version->anchor_date = $this->anchorDate;
        $version->notify_from = $this->intervalUnit === 'hours' ? $this->notifyFrom : null;
        $version->duration_minutes = filled($this->durationMinutes) ? (int) $this->durationMinutes : null;
        $version->save();

        $babyIds = $this->allChildren ? Baby::pluck('id')->all() : $this->targetBabyIds;
        $plan->babies()->sync($babyIds);

        $plan->startNotifications()->sync(
            collect($this->startNotificationIds)->mapWithKeys(fn ($id) => [$id => ['trigger' => 'start']])->all()
        );
        $plan->endNotifications()->sync(
            collect($this->endNotificationIds)->mapWithKeys(fn ($id) => [$id => ['trigger' => 'end']])->all()
        );

        $scheduler->rescheduleFor($plan);

        $this->showModal = false;
        $this->resetForm();
        $this->refreshCalendar();

        session()->flash('success', 'Plan saved.');
    }

    public function toggleEnabled(int $planId, PlanScheduler $scheduler): void
    {
        $plan = Plan::findOrFail($planId);
        $plan->update(['enabled' => ! $plan->enabled]);

        $scheduler->rescheduleFor($plan);

        $this->refreshCalendar();
    }

    public function promptDeletePlan(int $planId): void
    {
        $this->showModal = false;
        $this->deletingPlanId = $planId;
        $this->showDeleteModal = true;
    }

    public function closeDeleteModal(): void
    {
        $this->showDeleteModal = false;
        $this->deletingPlanId = null;
    }

    public function deletePlan(PlanScheduler $scheduler): void
    {
        $plan = Plan::findOrFail($this->deletingPlanId);

        $scheduler->cancelFor($plan);
        $plan->delete();

        $this->closeDeleteModal();
        $this->resetForm();
        $this->refreshCalendar();

        session()->flash('success', 'Plan deleted.');
    }

    /**
     * The events payload for Schedule-X: logged actions as blocks and planned
     * slots (done/missed/upcoming) from {@see PlanTimeline}. Times are ISO-8601
     * strings with an offset in the device timezone, which the JS turns into
     * the Temporal objects Schedule-X v4 requires.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getEvents(string $from, string $to, ?string $timezone = null): array
    {
        // The calendar's first fetch can race ahead of setDeviceTimezone(), so
        // the browser passes its own zone along; fall back to the stored one.
        $tz = in_array($timezone, timezone_identifiers_list(), true)
            ? $timezone
            : ($this->deviceTimezone ?: 'UTC');

        $rangeFrom = Carbon::parse($from, $tz)->startOfDay()->utc();
        $rangeUntil = Carbon::parse($to, $tz)->endOfDay()->utc();

        $babyIds = $this->activeBabyIds !== [] ? $this->activeBabyIds : Baby::pluck('id')->all();
        $babies = Baby::whereIn('id', $babyIds)->get();

        $actions = BabyAction::whereIn('baby_id', $babyIds)
            ->where(function ($q) use ($rangeFrom, $rangeUntil) {
                $q->whereBetween('started_at', [$rangeFrom, $rangeUntil])
                    ->orWhereNull('finished_at');
            })
            ->with(['baby', 'babyActionType'])
            ->get();

        $plans = Plan::with(['versions', 'babies', 'babyActionType'])->get();

        $slots = app(PlanTimeline::class)->project($plans, $babies, $actions, $rangeFrom, $rangeUntil, $tz);

        // A done slot is the action that satisfied it, so it is drawn once: the
        // logged action carries the ✓ instead of a second, overlapping block.
        $doneActionIds = collect($slots)->where('status', 'done')->pluck('matched_action_id')->filter()->all();

        $events = [];

        foreach ($actions as $action) {
            $start = $action->started_at->copy()->timezone($tz);
            $end = ($action->finished_at ?? ($action->babyActionType->is_instant
                ? $action->started_at->copy()->addMinutes(15)
                : now()))->copy()->timezone($tz);

            // Only a finished duration action has a real end time; instant,
            // ongoing and same-minute actions show their start only.
            $hasEnd = ! $action->babyActionType->is_instant
                && $action->finished_at !== null
                && $action->finished_at->greaterThan($action->started_at);

            // Instant actions (and ones finished the same minute) would otherwise
            // be zero-height, invisible blocks in the time grid.
            if ($end->lessThan($start->copy()->addMinutes(15))) {
                $end = $start->copy()->addMinutes(15);
            }

            $events[] = [
                'id' => 'action-'.$action->id,
                'title' => (in_array($action->id, $doneActionIds, true) ? '✓ ' : '').$action->babyActionType->name.' — '.$action->baby->name,
                'start' => $start->toIso8601String(),
                'end' => $end->toIso8601String(),
                'hasEnd' => $hasEnd,
                'calendarId' => 'type-'.$action->baby_action_type_id,
                'href' => route('baby_actions.edit', $action),
            ];
        }

        foreach ($slots as $slot) {
            if ($slot['status'] === 'done' && $slot['matched_action_id'] !== null) {
                continue;
            }

            $plan = $plans->firstWhere('id', $slot['plan_id']);
            $baby = $babies->firstWhere('id', $slot['baby_id']);

            $prefix = match (true) {
                $slot['status'] === 'missed' => '✗ ',
                $slot['is_next'] => '→ ',
                default => '',
            };

            $at = $slot['at']->copy()->timezone($tz);
            $end = $slot['duration_minutes'] !== null
                ? $at->copy()->addMinutes($slot['duration_minutes'])
                : $at->copy()->addMinutes(15);

            $events[] = [
                'id' => 'plan-'.$slot['plan_id'].'-'.$slot['baby_id'].'-'.$at->timestamp,
                'title' => $prefix.($plan?->babyActionType?->name ?? 'Plan').' — '.($baby?->name ?? ''),
                'start' => $at->toIso8601String(),
                'end' => $end->toIso8601String(),
                'hasEnd' => $slot['duration_minutes'] !== null,
                'calendarId' => 'planned',
                'planId' => $slot['plan_id'],
            ];
        }

        return $events;
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->babyActionTypeId = null;
        $this->allChildren = true;
        $this->targetBabyIds = [];
        $this->enabled = true;
        $this->startsOn = null;
        $this->endsOn = null;
        $this->intervalUnit = 'hours';
        $this->intervalValue = 3;
        $this->times = ['08:00'];
        $this->weekdays = [];
        $this->anchorDate = now()->toDateString();
        $this->notifyFrom = 'started_at';
        $this->durationMinutes = null;
        $this->startNotificationIds = [];
        $this->endNotificationIds = [];
        $this->newMessageTitle = '';
    }

    public function render()
    {
        $babies = Baby::orderBy('name')->get();
        $actionTypes = BabyActionType::orderBy('name')->get();
        $notifications = Notification::orderBy('title')->get();
        $plans = Plan::with(['babyActionType', 'babies', 'versions'])
            ->when($this->activeBabyIds !== [], fn ($query) => $query->where(
                fn ($query) => $query->where('all_children', true)
                    ->orWhereHas('babies', fn ($babies) => $babies->whereIn('babies.id', $this->activeBabyIds))
            ))
            ->orderBy('id', 'desc')
            ->get();

        return view('livewire.pages.planning.index', compact('babies', 'actionTypes', 'notifications', 'plans'));
    }
}
