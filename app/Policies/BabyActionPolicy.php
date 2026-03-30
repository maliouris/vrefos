<?php

namespace App\Policies;

use App\Models\BabyAction;
use App\Models\User;

class BabyActionPolicy
{
    public function update(User $user, BabyAction $babyAction): bool
    {
        return $user->id === $babyAction->baby->user_id;
    }
}
