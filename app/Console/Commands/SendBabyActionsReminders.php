<?php

namespace App\Console\Commands;

use App\Models\BabyAction;
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
        $babyActions = BabyAction::where('finished_at', '<', now()->subHours(2)->subMinutes(45))
            ->where('reminders', '<', 1)->get();

        $babyActions->each(fn ($babyAction) => $babyActionsService->sendReminder($babyAction));
    }
}
