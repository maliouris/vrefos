<?php

namespace App\Console\Commands;

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
        $babyActions = BabyAction::whereNotNull('finished_at')
            ->where('reminders', '<', 1)
            ->with('baby.user', 'babyActionType')
            ->get();

        foreach ($babyActions as $babyAction) {
            $user = $babyAction->baby->user;

            $setting = NotificationSetting::firstOrCreate(
                ['user_id' => $user->id, 'baby_action_type_id' => $babyAction->baby_action_type_id],
                ['enabled' => true, 'notify_after_minutes' => 180]
            );

            if (! $setting->enabled) {
                continue;
            }

            if ($babyAction->finished_at->diffInMinutes(now()) < $setting->notify_after_minutes) {
                continue;
            }

            $babyActionsService->sendReminder($babyAction);
        }
    }
}
