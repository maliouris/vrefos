<?php

namespace App\Contracts;

use App\Models\User;

interface PushNotifications
{
    /**
     * @param  non-empty-array<'web'|'android'|'ios'>  $devices
     * @param  non-empty-array<User>  $users
     */
    public function send(string $title, string $url, array $devices, array $users, string $body = ''): void;
}
