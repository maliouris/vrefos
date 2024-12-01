<?php

namespace App\Services;

use App\Contracts\PushNotifications;
use App\Models\BabyAction;
use Illuminate\Routing\UrlGenerator;

readonly class BabyActionsService {
    public function __construct(private PushNotifications $pushNotifications, private UrlGenerator $urlGenerator)
    {
    }

    public function sendReminder(BabyAction $babyAction): void {
        $action = strtolower($babyAction->babyActionType->name);

        $this->pushNotifications->send("Ready for the waaah concert?",
            $this->urlGenerator->to('/'), ['web'],
            [[$babyAction->baby->user]],
            "Tick-tock! You baby {$babyAction->baby->name} should {$action}. It's been almost 3 hours since the last time.");


        $babyAction->reminders++;
        $babyAction->save();
    }
}
