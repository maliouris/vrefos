<?php

namespace Tests\Feature;

use App\Enums\NotifyFrom;
use App\Models\BabyActionType;
use App\Models\NotificationSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_setting_can_be_created_and_updated(): void
    {
        $actionType = BabyActionType::factory()->create();

        $setting = NotificationSetting::create([
            'baby_action_type_id' => $actionType->id,
            'enabled' => true,
            'notify_after_minutes' => 180,
            'notify_from' => NotifyFrom::StartedAt,
        ]);

        $this->assertTrue($setting->enabled);
        $this->assertEquals(180, $setting->notify_after_minutes);

        $setting->update(['enabled' => false]);
        $this->assertFalse($setting->fresh()->enabled);
    }
}
