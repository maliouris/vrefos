<?php

namespace App\Services;

use App\Enums\NotifyFrom;
use App\Models\BabyAction;
use App\Models\BabyActionType;
use App\Models\NotificationSetting;
use Carbon\Carbon;
use Ikromjon\LocalNotifications\Facades\LocalNotifications;
use Illuminate\Support\Facades\DB;

class LocalNotificationScheduler
{
    public function scheduleFor(BabyAction $action): bool
    {
        $action->loadMissing(['baby', 'babyActionType']);

        $rules = NotificationSetting::where('baby_action_type_id', $action->baby_action_type_id)
            ->where('enabled', true)
            ->get();

        $scheduledKeys = [];

        foreach ($rules as $rule) {
            $fireAt = $this->calculateFireAt($action, $rule);

            if ($fireAt === null) {
                continue;
            }

            if ($fireAt->isPast()) {
                $fireAt = now()->addSeconds(1);
            }

            $key = $this->notificationId($action, $rule);

            $content = $this->resolveContent($action, $rule);

            $this->dispatchSchedule([
                'id' => $key,
                'title' => $content['title'],
                'body' => $content['body'],
                'at' => $fireAt->timestamp,
                'data' => ['action_id' => $action->id],
            ]);

            $scheduledKeys[] = $key;
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
     * @param  array{id: string, title: string, body: string, at: int, data: array{action_id: int}}  $payload
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
