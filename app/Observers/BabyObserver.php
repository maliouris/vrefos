<?php

namespace App\Observers;

use App\Models\Baby;
use App\Models\NotificationSetting;

final class BabyObserver
{
    public function created(Baby $baby): void
    {
        $allChildrenRuleIds = NotificationSetting::where('all_children', true)->pluck('id');

        $baby->notificationSettings()->syncWithoutDetaching($allChildrenRuleIds);
    }
}
