<?php

namespace App\Services;

use App\Enums\IntervalUnit;
use App\Enums\NotifyFrom;
use App\Models\Baby;
use App\Models\BabyAction;
use App\Models\Plan;
use App\Models\PlanVersion;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Projects a plan's recurrence into concrete slots for a date range and
 * matches them against logged actions (plan-vs-actual). Pure/stateless: it
 * only reads the plans/versions/actions handed to it, so it is safe to
 * unit-test without touching the scheduler or native runtime.
 */
class PlanTimeline
{
    public const SLOT_MATCH_TOLERANCE_MINUTES = 45;

    private const MAX_PROJECTION_STEPS = 500;

    /**
     * @param  Collection<int, Plan>  $plans  with `versions` and `babies` eager loaded
     * @param  Collection<int, Baby>  $babies  every baby that could be targeted
     * @param  Collection<int, BabyAction>  $actions  logged actions relevant to the range (any of the plans' types)
     * @return array<int, array{plan_id: int, baby_id: int, baby_action_type_id: int, at: Carbon, duration_minutes: ?int, status: string, is_next: bool, estimated: bool, matched_action_id: ?int}>
     */
    public function project(Collection $plans, Collection $babies, Collection $actions, Carbon $from, Carbon $until, string $timezone): array
    {
        $slots = [];

        foreach ($plans as $plan) {
            [$rangeFrom, $rangeUntil] = $this->clipRange($plan, $from, $until);

            if ($rangeFrom === null || $rangeFrom->gt($rangeUntil)) {
                continue;
            }

            $targetBabies = $plan->all_children
                ? $babies
                : $babies->whereIn('id', $plan->babies->pluck('id'));

            foreach ($targetBabies as $baby) {
                $babyActions = $actions
                    ->where('baby_id', $baby->id)
                    ->where('baby_action_type_id', $plan->baby_action_type_id)
                    ->sortBy('started_at')
                    ->values();

                $planSlots = $this->currentVersionUnit($plan) === IntervalUnit::Hours
                    ? $this->projectHoursChain($plan, $baby, $babyActions, $rangeFrom, $rangeUntil, $timezone)
                    : $this->projectClockDays($plan, $baby, $babyActions, $rangeFrom, $rangeUntil, $timezone);

                array_push($slots, ...$planSlots);
            }
        }

        return $this->markNext($slots);
    }

    private function currentVersionUnit(Plan $plan): ?IntervalUnit
    {
        return $plan->versions->sortByDesc('effective_from')->first()?->interval_unit;
    }

    /**
     * @return array{0: ?Carbon, 1: Carbon}
     */
    private function clipRange(Plan $plan, Carbon $from, Carbon $until): array
    {
        $rangeFrom = $from->copy();
        $rangeUntil = $until->copy();

        if ($plan->starts_on !== null) {
            $startsAt = Carbon::parse($plan->starts_on->toDateString().' 00:00:00', 'UTC');
            if ($rangeFrom->lt($startsAt)) {
                $rangeFrom = $startsAt;
            }
        }

        if ($plan->ends_on !== null) {
            $endsAt = Carbon::parse($plan->ends_on->toDateString().' 23:59:59', 'UTC');
            if ($rangeUntil->gt($endsAt)) {
                $rangeUntil = $endsAt;
            }
            if ($rangeFrom->gt($endsAt)) {
                return [null, $rangeUntil];
            }
        }

        return [$rangeFrom, $rangeUntil];
    }

    /**
     * @param  Collection<int, BabyAction>  $actions
     * @return array<int, array{plan_id: int, baby_id: int, baby_action_type_id: int, at: Carbon, duration_minutes: ?int, status: string, is_next: bool, estimated: bool, matched_action_id: ?int}>
     */
    private function projectClockDays(Plan $plan, Baby $baby, Collection $actions, Carbon $rangeFrom, Carbon $rangeUntil, string $timezone): array
    {
        $slots = [];
        $consumed = [];

        $cursor = $rangeFrom->copy()->timezone($timezone)->startOfDay();
        $lastDay = $rangeUntil->copy()->timezone($timezone)->startOfDay();

        for ($i = 0; $i < self::MAX_PROJECTION_STEPS && $cursor->lte($lastDay); $i++, $cursor->addDay()) {
            $version = PlanVersion::activeFor($plan, $cursor);

            if ($version === null) {
                continue;
            }

            foreach ($version->times ?: ['00:00'] as $time) {
                $at = Carbon::parse($cursor->toDateString().' '.$time, $timezone)->utc();

                if ($at->lt($rangeFrom) || $at->gt($rangeUntil)) {
                    continue;
                }

                $slots[] = [
                    'plan_id' => $plan->id,
                    'baby_id' => $baby->id,
                    'baby_action_type_id' => $plan->baby_action_type_id,
                    'at' => $at,
                    'duration_minutes' => $version->duration_minutes,
                    'estimated' => false,
                    'matched_action_id' => null,
                    'status' => 'upcoming',
                    'is_next' => false,
                ];
            }
        }

        return $this->matchSlots($slots, $actions, $consumed);
    }

    /**
     * @param  Collection<int, BabyAction>  $actions
     * @return array<int, array{plan_id: int, baby_id: int, baby_action_type_id: int, at: Carbon, duration_minutes: ?int, status: string, is_next: bool, estimated: bool, matched_action_id: ?int}>
     */
    private function projectHoursChain(Plan $plan, Baby $baby, Collection $actions, Carbon $rangeFrom, Carbon $rangeUntil, string $timezone): array
    {
        $priorAction = $actions->filter(fn (BabyAction $a) => $a->started_at->lt($rangeFrom))->sortByDesc('started_at')->first();
        $remaining = $actions->filter(fn (BabyAction $a) => $a->started_at->gte($rangeFrom))->sortBy('started_at')->values();
        $consumedActionIds = [];

        $referenceField = fn (PlanVersion $v) => ($v->notify_from ?? NotifyFrom::StartedAt) === NotifyFrom::FinishedAt ? 'finished_at' : 'started_at';

        if ($priorAction !== null) {
            $version = PlanVersion::activeFor($plan, $priorAction->started_at);
            $cursor = $version !== null ? $priorAction->{$referenceField($version)} : $rangeFrom;
        } elseif ($remaining->isNotEmpty()) {
            // No lookback available: the earliest known action becomes the
            // initial anchor (consumed silently — it is history, not a slot).
            $leading = $remaining->first();
            $version = PlanVersion::activeFor($plan, $leading->started_at);
            $cursor = $version !== null ? $leading->{$referenceField($version)} : $rangeFrom;
            $consumedActionIds[] = $leading->id;
        } else {
            $cursor = $rangeFrom;
        }

        $slots = [];
        $estimated = false;

        for ($i = 0; $i < self::MAX_PROJECTION_STEPS; $i++) {
            $version = PlanVersion::activeFor($plan, $cursor);

            if ($version === null) {
                break;
            }

            $slotAt = $cursor->copy()->addMinutes($version->interval_minutes);

            if ($slotAt->gt($rangeUntil)) {
                break;
            }

            $matched = $remaining->first(fn (BabyAction $a) => ! in_array($a->id, $consumedActionIds, true)
                && abs($a->started_at->diffInMinutes($slotAt, false)) <= self::SLOT_MATCH_TOLERANCE_MINUTES);

            if ($slotAt->gte($rangeFrom)) {
                $slots[] = [
                    'plan_id' => $plan->id,
                    'baby_id' => $baby->id,
                    'baby_action_type_id' => $plan->baby_action_type_id,
                    'at' => $slotAt,
                    'duration_minutes' => $version->duration_minutes,
                    'estimated' => $estimated && $matched === null,
                    'matched_action_id' => $matched?->id,
                    'status' => $matched !== null ? 'done' : ($slotAt->isPast() ? 'missed' : 'upcoming'),
                    'is_next' => false,
                ];
            }

            if ($matched !== null) {
                $consumedActionIds[] = $matched->id;
                $cursor = $matched->{$referenceField($version)};
                $estimated = false;
            } else {
                $cursor = $slotAt;
                $estimated = true;
            }
        }

        return $slots;
    }

    /**
     * Nearest-first matching of clock-based slots against logged actions,
     * within {@see SLOT_MATCH_TOLERANCE_MINUTES}. Each action matches at
     * most one slot.
     *
     * @param  array<int, array{plan_id: int, baby_id: int, baby_action_type_id: int, at: Carbon, duration_minutes: ?int, status: string, is_next: bool, estimated: bool, matched_action_id: ?int}>  $slots
     * @param  Collection<int, BabyAction>  $actions
     * @param  array<int, bool>  $consumed
     * @return array<int, array{plan_id: int, baby_id: int, baby_action_type_id: int, at: Carbon, duration_minutes: ?int, status: string, is_next: bool, estimated: bool, matched_action_id: ?int}>
     */
    private function matchSlots(array $slots, Collection $actions, array $consumed): array
    {
        $candidates = [];

        foreach ($slots as $slotIndex => $slot) {
            foreach ($actions as $action) {
                $diff = abs($action->started_at->diffInMinutes($slot['at'], false));

                if ($diff <= self::SLOT_MATCH_TOLERANCE_MINUTES) {
                    $candidates[] = ['slot' => $slotIndex, 'action' => $action->id, 'diff' => $diff];
                }
            }
        }

        usort($candidates, fn ($a, $b) => $a['diff'] <=> $b['diff']);

        $matchedSlots = [];
        $matchedActions = [];

        foreach ($candidates as $candidate) {
            if (isset($matchedSlots[$candidate['slot']]) || isset($matchedActions[$candidate['action']])) {
                continue;
            }

            $matchedSlots[$candidate['slot']] = $candidate['action'];
            $matchedActions[$candidate['action']] = true;
        }

        foreach ($slots as $index => &$slot) {
            if (isset($matchedSlots[$index])) {
                $slot['matched_action_id'] = $matchedSlots[$index];
                $slot['status'] = 'done';
            } else {
                $slot['status'] = $slot['at']->isPast() ? 'missed' : 'upcoming';
            }
        }

        return $slots;
    }

    /**
     * @param  array<int, array{plan_id: int, baby_id: int, baby_action_type_id: int, at: Carbon, duration_minutes: ?int, status: string, is_next: bool, estimated: bool, matched_action_id: ?int}>  $slots
     * @return array<int, array{plan_id: int, baby_id: int, baby_action_type_id: int, at: Carbon, duration_minutes: ?int, status: string, is_next: bool, estimated: bool, matched_action_id: ?int}>
     */
    private function markNext(array $slots): array
    {
        $upcoming = array_filter($slots, fn ($s) => $s['status'] === 'upcoming');

        if ($upcoming === []) {
            return $slots;
        }

        $nextKey = null;
        $nextAt = null;

        foreach ($upcoming as $key => $slot) {
            if ($nextAt === null || $slot['at']->lt($nextAt)) {
                $nextAt = $slot['at'];
                $nextKey = $key;
            }
        }

        if ($nextKey !== null) {
            $slots[$nextKey]['is_next'] = true;
        }

        return $slots;
    }
}
