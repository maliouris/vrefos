<?php

namespace App\Console\Commands;

use App\Enums\NotifyFrom;
use App\Models\BabyAction;
use App\Models\NotificationSetting;
use App\Services\BabyActionsService;
use Illuminate\Console\Command;

class SendBabyActionsReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:send-baby-action-reminder';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send baby action reminder when 2.45 hours has passed';

    /**
     * Execute the console command.
     */
    public function handle(BabyActionsService $babyActionsService): void
    {
        $babyActions = BabyAction::where('reminders', '<', 1)
            ->with('baby', 'babyActionType')
            ->get();

        foreach ($babyActions as $babyAction) {
            $setting = NotificationSetting::firstOrCreate(
                ['baby_action_type_id' => $babyAction->baby_action_type_id],
                ['enabled' => true, 'notify_after_minutes' => 180, 'notify_from' => NotifyFrom::StartedAt]
            );

            if (! $setting->enabled) {
                continue;
            }

            $referenceTime = $setting->notify_from === NotifyFrom::FinishedAt
                ? $babyAction->finished_at
                : $babyAction->started_at;

            if (is_null($referenceTime)) {
                continue;
            }

            if ($referenceTime->diffInMinutes(now()) < $setting->notify_after_minutes) {
                continue;
            }

            $babyActionsService->sendReminder($babyAction);
        }
    }
}
