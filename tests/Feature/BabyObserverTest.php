<?php

namespace Tests\Feature;

use App\Enums\NotifyFrom;
use App\Models\Baby;
use App\Models\BabyActionType;
use App\Models\NotificationSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BabyObserverTest extends TestCase
{
    use RefreshDatabase;

    private function ruleFor(BabyActionType $type, array $overrides = []): NotificationSetting
    {
        return NotificationSetting::create(array_merge([
            'baby_action_type_id' => $type->id,
            'enabled' => true,
            'notify_after_minutes' => 180,
            'notify_from' => NotifyFrom::StartedAt,
            'title' => 'Time to check on your baby!',
        ], $overrides));
    }

    public function test_new_baby_is_attached_to_all_children_rules_only(): void
    {
        $type = BabyActionType::factory()->create();
        $allChildrenRule = $this->ruleFor($type, ['all_children' => true]);
        $specificRule = $this->ruleFor($type, ['all_children' => false]);

        $baby = Baby::factory()->create();

        // Seeded default rules also target all children, so assert membership
        // rather than exact equality.
        $attachedRuleIds = $baby->notificationSettings()->pluck('notification_settings.id')->all();
        $this->assertContains($allChildrenRule->id, $attachedRuleIds);
        $this->assertNotContains($specificRule->id, $attachedRuleIds);
        $this->assertEmpty($specificRule->babies()->pluck('babies.id')->all());
    }

    public function test_deleting_a_baby_removes_its_pivot_rows(): void
    {
        $type = BabyActionType::factory()->create();
        $rule = $this->ruleFor($type, ['all_children' => true]);

        $baby = Baby::factory()->create();
        $this->assertDatabaseHas('baby_notification_setting', [
            'baby_id' => $baby->id,
            'notification_setting_id' => $rule->id,
        ]);

        $baby->delete();

        $this->assertDatabaseMissing('baby_notification_setting', [
            'baby_id' => $baby->id,
        ]);
    }
}
