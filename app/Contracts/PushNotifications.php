<?php

namespace App\Contracts;

use App\Models\BabyAction;

interface PushNotifications {
    public function sendBabyActionReminder(BabyAction $babyAction): void;
}
