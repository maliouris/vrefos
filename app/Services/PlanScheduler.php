<?php

namespace App\Services;

use App\Enums\IntervalUnit;
use App\Enums\NotificationTrigger;
use App\Enums\NotifyFrom;
use App\Models\Baby;
use App\Models\BabyAction;
use App\Models\Notification as PlanNotification;
use App\Models\Plan;
use App\Models\PlanVersion;
use Ikromjon\LocalNotifications\Facades\LocalNotifications;
use Illuminate\Support\Carbon;

class PlanScheduler
{
    /**
     * The app-wide notification chime, shipped into the native builds by the
     * vrefos/native-assets plugin. Android bakes a channel's sound in at first
     * use, so replacing the audio under the same filename won't update
     * already-installed devices — rename the file to force a new channel.
     */
    public const SOUND_NAME = 'brefos_notification.wav';

    /**
     * A generic cap on how many times a repeating projection loop is allowed
     * to advance while searching for an occurrence — guards against runaway
     * loops from malformed data (e.g. a zero-length interval).
     */
    private const MAX_PROJECTION_STEPS = 1000;

    public function scheduleFor(Plan $plan): bool
    {
        if (! $plan->enabled) {
            $this->clearBookkeeping($plan);

            return false;
        }

        $scheduledKeys = [];

        foreach ($this->planFor($plan) as $payload) {
            $this->dispatchSchedule($payload);
            $scheduledKeys[] = $payload['id'];
        }

        if ($scheduledKeys === []) {
            $this->clearBookkeeping($plan);

            return false;
        }

        $plan->notification_scheduled_at = now();
        $plan->scheduled_notification_keys = $scheduledKeys;
        $plan->saveQuietly();

        return true;
    }

    public function cancelFor(Plan $plan): void
    {
        foreach ($plan->scheduled_notification_keys ?? [] as $key) {
            $this->dispatchCancel($key);
        }

        $plan->notification_scheduled_at = null;
        $plan->scheduled_notification_keys = null;

        // When cancelling because the plan was deleted, saving would
        // re-insert the row (exists is already false at that point).
        if ($plan->exists) {
            $plan->saveQuietly();
        }
    }

    public function rescheduleFor(Plan $plan): void
    {
        $this->cancelFor($plan);
        $this->scheduleFor($plan);
    }

    public function rescheduleAll(): int
    {
        $rescheduled = 0;

        Plan::with(['babyActionType', 'babies', 'versions', 'notifications'])
            ->each(function (Plan $plan) use (&$rescheduled) {
                $this->cancelFor($plan);
                if ($this->scheduleFor($plan)) {
                    $rescheduled++;
                }
            });

        return $rescheduled;
    }

    /**
     * Re-anchors every enabled Hours-unit plan for the action's type, since
     * logging (creating/updating/deleting) an action resets the interval
     * chain for whichever plans target that action's type and baby.
     */
    public function reanchorForAction(BabyAction $action): void
    {
        $plans = Plan::where('baby_action_type_id', $action->baby_action_type_id)
            ->where('enabled', true)
            ->with(['babies', 'versions', 'notifications'])
            ->get();

        foreach ($plans as $plan) {
            $version = $plan->currentVersion();

            if ($version === null || $version->interval_unit !== IntervalUnit::Hours) {
                continue;
            }

            if (! $plan->all_children && ! $plan->babies->pluck('id')->contains($action->baby_id)) {
                continue;
            }

            $this->rescheduleFor($plan);
        }
    }

    private function clearBookkeeping(Plan $plan): void
    {
        $plan->notification_scheduled_at = null;
        $plan->scheduled_notification_keys = null;
        $plan->saveQuietly();
    }

    /**
     * The full set of native schedule payloads for a plan: one per targeted
     * baby × attached notification × trigger × occurrence (a clock-based
     * plan with multiple `times` entries produces one occurrence per entry).
     *
     * @return array<int, array{id: string, title: string, body: string, at: int, soundName: string, data: array{plan_id: int}, repeat?: string, repeatDays?: array<int, int>, repeatIntervalSeconds?: int, repeatCount?: int}>
     */
    private function planFor(Plan $plan): array
    {
        $version = $plan->currentVersion();

        if ($version === null) {
            return [];
        }

        $plan->loadMissing(['babyActionType', 'babies', 'notifications']);

        $babies = $plan->all_children ? Baby::all() : $plan->babies;

        if ($babies->isEmpty()) {
            return [];
        }

        $payloads = [];

        foreach ($babies as $baby) {
            foreach ($this->occurrenceEntriesFor($plan, $version, $baby) as $entry) {
                $notifications = $entry['trigger'] === NotificationTrigger::Start
                    ? $plan->startNotifications
                    : $plan->endNotifications;

                foreach ($notifications as $notification) {
                    $payloads[] = $this->buildPayload($plan, $baby, $notification, $entry);
                }
            }
        }

        return $payloads;
    }

    /**
     * The start/end occurrence entries for one baby, already clamped/limited
     * to the plan's `starts_on`/`ends_on` range. Each entry carries the
     * trigger, the computed `at`, an optional `timeIndex` (clock-based
     * plans with multiple `times`), and any native repeat parameters.
     *
     * @return array<int, array{trigger: NotificationTrigger, at: Carbon, timeIndex: ?int, recurrence: array<string, mixed>}>
     */
    private function occurrenceEntriesFor(Plan $plan, PlanVersion $version, Baby $baby): array
    {
        return $version->interval_unit === IntervalUnit::Hours
            ? $this->hoursEntriesFor($plan, $version, $baby)
            : $this->clockEntriesFor($plan, $version);
    }

    /**
     * @return array<int, array{trigger: NotificationTrigger, at: Carbon, timeIndex: ?int, recurrence: array<string, mixed>}>
     */
    private function hoursEntriesFor(Plan $plan, PlanVersion $version, Baby $baby): array
    {
        $lastAction = BabyAction::where('baby_id', $baby->id)
            ->where('baby_action_type_id', $plan->baby_action_type_id)
            ->orderByDesc('started_at')
            ->first();

        $entries = [];

        $referenceField = ($version->notify_from ?? NotifyFrom::StartedAt) === NotifyFrom::FinishedAt
            ? 'finished_at'
            : 'started_at';

        $reference = $lastAction?->{$referenceField};

        $startAt = $reference !== null
            ? $reference->copy()->addMinutes($version->interval_minutes)
            : now()->addMinutes($version->interval_minutes);

        if ($startAt->isPast()) {
            $startAt = now()->addSecond();
        }

        $startAt = $this->clampToRange($plan, $startAt);

        if ($startAt !== null) {
            $entries[] = [
                'trigger' => NotificationTrigger::Start,
                'at' => $startAt,
                'timeIndex' => null,
                'recurrence' => [],
            ];
        }

        if ($version->duration_minutes !== null && $lastAction?->finished_at !== null) {
            $endAt = $lastAction->finished_at->copy()->addMinutes($version->duration_minutes);

            if ($endAt->isPast()) {
                $endAt = now()->addSecond();
            }

            $endAt = $this->clampToRange($plan, $endAt);

            if ($endAt !== null) {
                $entries[] = [
                    'trigger' => NotificationTrigger::End,
                    'at' => $endAt,
                    'timeIndex' => null,
                    'recurrence' => [],
                ];
            }
        }

        return $entries;
    }

    /**
     * @return array<int, array{trigger: NotificationTrigger, at: Carbon, timeIndex: ?int, recurrence: array<string, mixed>}>
     */
    private function clockEntriesFor(Plan $plan, PlanVersion $version): array
    {
        $times = $version->times ?: ['00:00'];
        $now = now();
        $entries = [];

        foreach ($times as $timeIndex => $time) {
            [$startAt, $recurrence] = $this->nextStartOccurrence($plan, $version, $time, $now);

            $startAt = $this->clampToRange($plan, $startAt);

            if ($startAt !== null) {
                $recurrenceWithCount = $this->withRepeatCount($plan, $version, $startAt, $recurrence);

                $entries[] = [
                    'trigger' => NotificationTrigger::Start,
                    'at' => $startAt,
                    'timeIndex' => $timeIndex,
                    'recurrence' => $recurrenceWithCount,
                ];
            }

            if ($version->duration_minutes === null) {
                continue;
            }

            [$rawStartAt] = $this->nextStartOccurrence($plan, $version, $time, $now);
            $endAt = $this->clampToRange($plan, $rawStartAt->copy()->addMinutes($version->duration_minutes));

            if ($endAt !== null) {
                $recurrenceWithCount = $this->withRepeatCount($plan, $version, $endAt, $recurrence);

                $entries[] = [
                    'trigger' => NotificationTrigger::End,
                    'at' => $endAt,
                    'timeIndex' => $timeIndex,
                    'recurrence' => $recurrenceWithCount,
                ];
            }
        }

        return $entries;
    }

    /**
     * The next occurrence (and native repeat config) for one `times` entry,
     * branching per {@see IntervalUnit}.
     *
     * @return array{0: Carbon, 1: array<string, mixed>}
     */
    private function nextStartOccurrence(Plan $plan, PlanVersion $version, string $time, Carbon $now): array
    {
        $tz = $plan->device_timezone ?: 'UTC';

        return match ($version->interval_unit) {
            IntervalUnit::Days => $version->interval_minutes === 1440
                ? [$this->nextDaily($tz, $time, $now), ['repeat' => 'daily']]
                : [
                    $this->nextCadence($tz, $version->anchor_date, (int) round($version->interval_minutes / 1440), $time, $now),
                    ['repeatIntervalSeconds' => $version->interval_minutes * 60],
                ],
            IntervalUnit::Weeks => ! empty($version->weekdays)
                ? [$this->nextWeekday($tz, $version->weekdays, $time, $now), ['repeatDays' => $version->weekdays]]
                : [
                    $this->nextCadence($tz, $version->anchor_date, (int) round($version->interval_minutes / 1440), $time, $now),
                    ['repeatIntervalSeconds' => $version->interval_minutes * 60],
                ],
            IntervalUnit::Months => [$this->nextMonthly($tz, $version->anchor_date, $time, $now), ['repeat' => 'monthly']],
            IntervalUnit::Hours => [$now, []],
        };
    }

    private function nextDaily(string $tz, string $time, Carbon $from): Carbon
    {
        $candidate = $this->localTime($tz, $from->copy()->timezone($tz)->toDateString(), $time);

        if ($candidate->lt($from)) {
            $candidate->addDay();
        }

        return $candidate;
    }

    /**
     * @param  array<int, int>  $weekdays  ISO weekdays (1=Mon..7=Sun)
     */
    private function nextWeekday(string $tz, array $weekdays, string $time, Carbon $from): Carbon
    {
        for ($offset = 0; $offset <= 7; $offset++) {
            $candidateDate = $from->copy()->timezone($tz)->addDays($offset);

            if (! in_array($candidateDate->isoWeekday(), $weekdays, true)) {
                continue;
            }

            $candidate = $this->localTime($tz, $candidateDate->toDateString(), $time);

            if ($candidate->gte($from)) {
                return $candidate;
            }
        }

        // Unreachable when $weekdays is non-empty (7-day window always matches).
        return $this->localTime($tz, $from->copy()->timezone($tz)->toDateString(), $time)->addWeek();
    }

    private function nextCadence(string $tz, Carbon $anchorDate, int $intervalDays, string $time, Carbon $from): Carbon
    {
        $intervalDays = max($intervalDays, 1);

        $candidate = $this->localTime($tz, $anchorDate->toDateString(), $time);

        for ($i = 0; $i < self::MAX_PROJECTION_STEPS && $candidate->lt($from); $i++) {
            $candidate->addDays($intervalDays);
        }

        return $candidate;
    }

    private function nextMonthly(string $tz, Carbon $anchorDate, string $time, Carbon $from): Carbon
    {
        $day = (int) $anchorDate->format('d');
        $cursor = $from->copy()->timezone($tz)->startOfMonth();

        for ($i = 0; $i < self::MAX_PROJECTION_STEPS; $i++) {
            $useDay = min($day, $cursor->daysInMonth);
            $candidate = $this->localTime($tz, $cursor->copy()->day($useDay)->toDateString(), $time);

            if ($candidate->gte($from)) {
                return $candidate;
            }

            $cursor->addMonthNoOverflow();
        }

        return $candidate;
    }

    private function localTime(string $tz, string $date, string $time): Carbon
    {
        return Carbon::parse($date.' '.$time, $tz)->utc();
    }

    /**
     * Clamp an occurrence into the plan's `[starts_on, ends_on]` range
     * (device-local days): push a too-early occurrence into range, drop one
     * that falls after `ends_on`.
     */
    private function clampToRange(Plan $plan, Carbon $at): ?Carbon
    {
        $tz = $plan->device_timezone ?: 'UTC';

        if ($plan->starts_on !== null) {
            $rangeStart = $this->localTime($tz, $plan->starts_on->toDateString(), '00:00');

            if ($at->lt($rangeStart)) {
                $at = $rangeStart;
            }
        }

        if ($plan->ends_on !== null) {
            $rangeEnd = $this->localTime($tz, $plan->ends_on->toDateString(), '23:59:59');

            if ($at->gt($rangeEnd)) {
                return null;
            }
        }

        return $at;
    }

    /**
     * Adds `repeatCount` for a bounded repeating plan (the plugin has no
     * concept of `ends_on`, so it must be told how many times to fire).
     * Open-ended plans (`ends_on` null) omit it — unchanged native behavior.
     *
     * @param  array<string, mixed>  $recurrence
     * @return array<string, mixed>
     */
    private function withRepeatCount(Plan $plan, PlanVersion $version, Carbon $firstAt, array $recurrence): array
    {
        if ($plan->ends_on === null || $recurrence === []) {
            return $recurrence;
        }

        $tz = $plan->device_timezone ?: 'UTC';
        $rangeEnd = $this->localTime($tz, $plan->ends_on->toDateString(), '23:59:59');

        if ($firstAt->gt($rangeEnd)) {
            return $recurrence;
        }

        $count = 1;
        $cursor = $firstAt->copy();

        for ($i = 0; $i < self::MAX_PROJECTION_STEPS; $i++) {
            $next = $this->advanceOccurrence($version, $recurrence, $cursor, $tz);

            if ($next->gt($rangeEnd)) {
                break;
            }

            $count++;
            $cursor = $next;
        }

        $recurrence['repeatCount'] = $count;

        return $recurrence;
    }

    /**
     * @param  array<string, mixed>  $recurrence
     */
    private function advanceOccurrence(PlanVersion $version, array $recurrence, Carbon $from, string $tz): Carbon
    {
        if (isset($recurrence['repeatIntervalSeconds'])) {
            return $from->copy()->addSeconds($recurrence['repeatIntervalSeconds']);
        }

        if (isset($recurrence['repeatDays'])) {
            return $this->nextWeekday($tz, $recurrence['repeatDays'], $from->copy()->timezone($tz)->format('H:i'), $from->copy()->addMinute());
        }

        if (($recurrence['repeat'] ?? null) === 'monthly') {
            return $from->copy()->addMonthNoOverflow();
        }

        return $from->copy()->addDay();
    }

    /**
     * @param  array{trigger: NotificationTrigger, at: Carbon, timeIndex: ?int, recurrence: array<string, mixed>}  $entry
     * @return array{id: string, title: string, body: string, at: int, soundName: string, data: array{plan_id: int}, repeat?: string, repeatDays?: array<int, int>, repeatIntervalSeconds?: int, repeatCount?: int}
     */
    private function buildPayload(Plan $plan, Baby $baby, PlanNotification $notification, array $entry): array
    {
        $version = $plan->currentVersion();

        return array_merge([
            'id' => $this->key($plan, $baby, $notification, $entry),
            'title' => $this->applyPlaceholders($notification->title, $plan, $baby, $version),
            'body' => blank($notification->description) ? '' : $this->applyPlaceholders($notification->description, $plan, $baby, $version),
            'at' => $entry['at']->timestamp,
            'soundName' => self::SOUND_NAME,
            'data' => ['plan_id' => $plan->id],
        ], $entry['recurrence']);
    }

    /**
     * @param  array{trigger: NotificationTrigger, at: Carbon, timeIndex: ?int, recurrence: array<string, mixed>}  $entry
     */
    private function key(Plan $plan, Baby $baby, PlanNotification $notification, array $entry): string
    {
        $key = 'plan-'.$plan->id.'-baby-'.$baby->id.'-notif-'.$notification->id.'-'.$entry['trigger']->value;

        if ($entry['timeIndex'] !== null) {
            $key .= '-t'.$entry['timeIndex'];
        }

        return $key;
    }

    private function applyPlaceholders(string $message, Plan $plan, Baby $baby, ?PlanVersion $version): string
    {
        $interval = $version !== null
            ? rtrim(rtrim(number_format($version->intervalValue(), 1), '0'), '.').' '.$version->interval_unit->value
            : '';

        return str_replace(
            ['#{baby}', '#{action}', '#{interval}'],
            [$baby->name, strtolower($plan->babyActionType->name), $interval],
            $message,
        );
    }

    /**
     * Hand a scheduled-notification payload to the native plugin.
     *
     * Guarded so it is a no-op on web/tests (no native runtime); extracted as a
     * seam so tests can capture the payload without the native runtime present.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function dispatchSchedule(array $payload): void
    {
        if (function_exists('nativephp_call') && class_exists(LocalNotifications::class)) {
            LocalNotifications::schedule($payload);
        }
    }

    /**
     * Cancel a scheduled notification by key via the native plugin.
     *
     * Guarded so it is a no-op on web/tests; extracted as a seam for testing.
     */
    protected function dispatchCancel(string $key): void
    {
        if (function_exists('nativephp_call') && class_exists(LocalNotifications::class)) {
            LocalNotifications::cancel($key);
        }
    }
}
