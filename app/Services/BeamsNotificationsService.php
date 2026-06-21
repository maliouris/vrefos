<?php

namespace App\Services;

use App\Contracts\PushNotifications;

readonly class BeamsNotificationsService implements PushNotifications
{
    public function __construct(private BeamsClientService $client) {}

    public function send(string $title, string $url, array $devices, string $body = ''): void
    {
        $payload = array_map(function ($device) use ($title, $body, $url) {
            return [
                $device => [
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                        'deep_link' => $url,
                    ],
                ],
            ];
        }, $devices);

        $this->client->publishToInterests(['vrefos-default'], $payload);
    }
}
