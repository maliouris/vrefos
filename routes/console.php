<?php

use App\Console\Commands\SendBabyActionsReminders;
use Illuminate\Support\Facades\Schedule;

Schedule::command(SendBabyActionsReminders::class)->everyMinute();
