<?php

use App\Console\Commands\SendBabyActionsReminders;
use App\Console\Commands\SyncToServer;
use Illuminate\Support\Facades\Schedule;

Schedule::command(SendBabyActionsReminders::class)->everyMinute();

// Mobile-only: periodically push dirty records to the remote server
if (function_exists('nativephp_call')) {
    Schedule::command(SyncToServer::class)->everyFiveMinutes()->withoutOverlapping();
}
