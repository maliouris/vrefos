<?php

namespace App\Services;

use App\Enums\NotifyFrom;
use App\Models\BabyAction;
use App\Models\BabyActionType;
use App\Models\NotificationSetting;
use Carbon\Carbon;
use Ikromjon\LocalNotifications\Facades\LocalNotifications;
use Illuminate\Support\Facades\DB;

final class LocalNotificationScheduler
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

            if ($fireAt === null || $fireAt->isPast()) {
                continue;
            }

            $key = $this->notificationId($action, $rule);

            if (function_exists('nativephp_call') && class_exists('Ikromjon\LocalNotifications\Facades\LocalNotifications')) {
                $content = $this->resolveContent($action, $rule);

                LocalNotifications::schedule([
                    'id' => $key,
                    'title' => $content['title'],
                    'body' => $content['body'],
                    'at' => $fireAt->timestamp,
                    'data' => ['action_id' => $action->id],
                ]);
            }

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

        if (function_exists('nativephp_call') && class_exists('Ikromjon\LocalNotifications\Facades\LocalNotifications')) {
            foreach ($keys as $key) {
                LocalNotifications::cancel($key);
            }
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
     * Resolve the notification title and body for a rule, applying the rule's
     * custom message (with placeholders) or falling back to the default text.
     *
     * @return array{title: string, body: string}
     */
    private function resolveContent(BabyAction $action, NotificationSetting $rule): array
    {
        $actionTypeName = strtolower($action->babyActionType->name);

        $body = blank($rule->message)
            ? 'Your baby needs '.$actionTypeName.'.'
            : $this->applyPlaceholders($rule->message, $action, $rule);

        return [
            'title' => 'Time to '.$actionTypeName.'!',
            'body' => $body,
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
