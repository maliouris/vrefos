<?php

namespace App\Services;

use App\Contracts\BeamsClient;
use App\Contracts\PushNotifications;

readonly class BeamsNotificationsService implements PushNotifications {
    public function __construct(private BeamsClient $client)
    {
    }

    public function send(string $title, string $url, array $devices, array $users, string $body = ''): void
    {
        $payload = array_map(function ($device) use ($title, $body, $url) {
            return [
                $device => [
                    "notification" => [
                        "title" => $title,
                        "body" => $body,
                        "deep_link" => $url
                    ]
                ]
            ];
        }, $devices);

        if (!empty($users)) {
            $beamsUsers = array_map(fn ($user) => strval($user->id), $users);
            $this->client->publishToUsers($beamsUsers, $payload);
        }
    }
}
