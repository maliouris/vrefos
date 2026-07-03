<?php

namespace App\Services;

use App\Enums\NotifyFrom;
use App\Models\BabyAction;
use App\Models\BabyActionType;
use App\Models\NotificationSetting;
use Carbon\Carbon;
use Ikromjon\LocalNotifications\Facades\LocalNotifications;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LocalNotificationScheduler
{
    /**
     * The app-wide notification chime, shipped into the native builds by the
     * vrefos/native-assets plugin. Android bakes a channel's sound in at first
     * use, so replacing the audio under the same filename won't update
     * already-installed devices — rename the file to force a new channel.
     */
    public const SOUND_NAME = 'brefos_notification.wav';

    public function scheduleFor(BabyAction $action): bool
    {
        $scheduledKeys = [];

        foreach ($this->planFor($action) as $planned) {
            $fireAt = $planned['fire_at'];

            if ($fireAt->isPast()) {
                $fireAt = now()->addSeconds(1);
            }

            $this->dispatchSchedule([
                'id' => $planned['key'],
                'title' => $planned['title'],
                'body' => $planned['body'],
                'at' => $fireAt->timestamp,
                'soundName' => self::SOUND_NAME,
                'data' => ['action_id' => $action->id],
            ]);

            $scheduledKeys[] = $planned['key'];
        }

        if ($scheduledKeys === []) {
            $action->notification_scheduled_at = null;
            $action->scheduled_notification_keys = null;
            $action->saveQuietly();

            return false;
        }

        $action->notification_scheduled_at = now();
        $action->scheduled_notification_keys = $scheduledKeys;
        $action->saveQuietly();

        return true;
    }

    /**
     * The reminders that would be scheduled for an action, for display purposes.
     *
     * Unlike the keys persisted by {@see scheduleFor()}, each planned reminder
     * keeps its true (un-clamped) `fire_at`, so a caller can tell upcoming
     * reminders apart from overdue ones.
     *
     * @return Collection<int, array{key: string, rule: NotificationSetting, fire_at: Carbon, title: string, body: string}>
     */
    public function upcomingFor(BabyAction $action): Collection
    {
        return collect($this->planFor($action));
    }

    /**
     * Resolve the eligible reminders for an action, keeping the true `fire_at`
     * (not clamped to the future). Shared by {@see scheduleFor()} and
     * {@see upcomingFor()} so eligibility and fire-time logic live in one place.
     *
     * @return array<int, array{key: string, rule: NotificationSetting, fire_at: Carbon, title: string, body: string}>
     */
    private function planFor(BabyAction $action): array
    {
        $action->loadMissing(['baby', 'babyActionType']);

        $rules = NotificationSetting::where('baby_action_type_id', $action->baby_action_type_id)
            ->where('enabled', true)
            ->with('babies:id')
            ->get();

        $planned = [];

        foreach ($rules as $rule) {
            if (! $rule->all_children && ! $rule->babies->pluck('id')->contains($action->baby_id)) {
                continue;
            }

            $fireAt = $this->calculateFireAt($action, $rule);

            if ($fireAt === null) {
                continue;
            }

            $content = $this->resolveContent($action, $rule);

            $planned[] = [
                'key' => $this->notificationId($action, $rule),
                'rule' => $rule,
                'fire_at' => $fireAt,
                'title' => $content['title'],
                'body' => $content['body'],
            ];
        }

        return $planned;
    }

    public function cancelFor(BabyAction $action): void
    {
        $keys = $action->scheduled_notification_keys ?? [];

        foreach ($keys as $key) {
            $this->dispatchCancel($key);
        }

        $action->notification_scheduled_at = null;
        $action->scheduled_notification_keys = null;
        $action->saveQuietly();
    }

    public function rescheduleFor(BabyAction $action): void
    {
        $this->cancelFor($action);
        $this->scheduleFor($action);
    }

    public function cancelAllForType(BabyActionType $type): int
    {
        $actions = BabyAction::where('baby_action_type_id', $type->id)
            ->with(['baby', 'babyActionType'])
            ->get();

        foreach ($actions as $action) {
            $this->cancelFor($action);
        }

        return $actions->count();
    }

    public function rescheduleAllForType(BabyActionType $type): int
    {
        $actions = BabyAction::where('baby_action_type_id', $type->id)
            ->with(['baby', 'babyActionType'])
            ->get();

        $rescheduled = 0;

        DB::transaction(function () use ($actions, &$rescheduled) {
            foreach ($actions as $action) {
                $this->cancelFor($action);
                if ($this->scheduleFor($action)) {
                    $rescheduled++;
                }
            }
        });

        return $rescheduled;
    }

    /**
     * Hand a scheduled-notification payload to the native plugin.
     *
     * Guarded so it is a no-op on web/tests (no native runtime); extracted as a
     * seam so tests can capture the payload without the native runtime present.
     *
     * @param  array{id: string, title: string, body: string, at: int, soundName: string, data: array{action_id: int}}  $payload
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

    private function calculateFireAt(BabyAction $action, NotificationSetting $rule): ?Carbon
    {
        $referenceTime = $rule->notify_from === NotifyFrom::FinishedAt
            ? $action->finished_at
            : $action->started_at;

        if ($referenceTime === null) {
            return null;
        }

        return $referenceTime->copy()->addMinutes($rule->notify_after_minutes);
    }

    /**
     * Resolve the notification title and body for a rule. The title is required;
     * the description is optional. Placeholders are applied to both.
     *
     * @return array{title: string, body: string}
     */
    private function resolveContent(BabyAction $action, NotificationSetting $rule): array
    {
        return [
            'title' => $this->applyPlaceholders($rule->title, $action, $rule),
            'body' => blank($rule->description)
                ? ''
                : $this->applyPlaceholders($rule->description, $action, $rule),
        ];
    }

    private function applyPlaceholders(string $message, BabyAction $action, NotificationSetting $rule): string
    {
        return str_replace(
            ['#{minutes}', '#{action}', '#{baby}'],
            [(string) $rule->notify_after_minutes, strtolower($action->babyActionType->name), $action->baby->name],
            $message,
        );
    }

    private function notificationId(BabyAction $action, NotificationSetting $rule): string
    {
        return 'action-'.$action->id.'-setting-'.$rule->id;
    }
}
