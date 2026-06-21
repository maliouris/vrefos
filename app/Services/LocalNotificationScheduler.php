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

        $setting = NotificationSetting::firstOrCreate(
            ['baby_action_type_id' => $action->baby_action_type_id],
            ['enabled' => true, 'notify_after_minutes' => 180, 'notify_from' => NotifyFrom::StartedAt]
        );

        if (! $setting->enabled) {
            return false;
        }

        $fireAt = $this->calculateFireAt($action, $setting);

        if ($fireAt === null || $fireAt->isPast()) {
            return false;
        }

        if (function_exists('nativephp_call') && class_exists('Ikromjon\LocalNotifications\Facades\LocalNotifications')) {
            $actionTypeName = strtolower($action->babyActionType->name);

            LocalNotifications::schedule([
                'id' => $this->notificationId($action),
                'title' => 'Time to '.$actionTypeName.'!',
                'body' => 'Your baby needs '.$actionTypeName.'.',
                'at' => $fireAt->timestamp,
                'data' => ['action_id' => $action->id],
            ]);
        }

        $action->notification_scheduled_at = now();
        $action->saveQuietly();

        return true;
    }

    public function cancelFor(BabyAction $action): void
    {
        if (function_exists('nativephp_call') && class_exists('Ikromjon\LocalNotifications\Facades\LocalNotifications')) {
            LocalNotifications::cancel(
                $this->notificationId($action)
            );
        }

        $action->notification_scheduled_at = null;
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
            ->whereNotNull('notification_scheduled_at')
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
            ->whereNotNull('notification_scheduled_at')
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

    private function calculateFireAt(BabyAction $action, NotificationSetting $setting): ?Carbon
    {
        $referenceTime = $setting->notify_from === NotifyFrom::FinishedAt
            ? $action->finished_at
            : $action->started_at;

        if ($referenceTime === null) {
            return null;
        }

        return $referenceTime->copy()->addMinutes($setting->notify_after_minutes);
    }

    private function notificationId(BabyAction $action): string
    {
        return 'action-'.$action->id;
    }
}
