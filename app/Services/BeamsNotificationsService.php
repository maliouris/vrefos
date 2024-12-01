<?php

namespace App\Services;

use App\Contracts\BeamsClient;
use App\Contracts\PushNotifications;
use App\Models\BabyAction;
use Illuminate\Routing\UrlGenerator;

readonly class BeamsNotificationsService implements PushNotifications {
    public function __construct(private BeamsClient $client, private UrlGenerator $urlGenerator)
    {
    }

    public function sendBabyActionReminder(BabyAction $babyAction): void {
        $action = strtolower($babyAction->babyActionType->name);

        $this->client->publishToUsers([strval($babyAction->baby->user->id)], [
            "web" => [
                "notification" => [
                    "title" => "Ready for the waaah concert?",
                    "body" => "Tick-tock! You baby {$babyAction->baby->name} should {$action}. It's been almost 3 hours since the last time.",
                    "deep_link" => $this->urlGenerator->to('/')
                ]
            ]
        ]);
    }
}
