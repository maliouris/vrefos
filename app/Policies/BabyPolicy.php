<?php

namespace App\Policies;

use App\Models\Baby;
use App\Models\User;

class BabyPolicy
{
    public function update(User $user, Baby $baby): bool
    {
        return $user->id === $baby->user_id;
    }
}
