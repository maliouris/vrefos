<?php

namespace App\Contracts;

interface PushNotifications
{
    /**
     * @param  non-empty-array<'web'|'android'|'ios'>  $devices
     */
    public function send(string $title, string $url, array $devices, string $body = ''): void;
}
