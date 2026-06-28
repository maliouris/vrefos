<?php

namespace Tests\Feature;

use App\Enums\NotifyFrom;
use App\Models\BabyActionType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DefaultNotificationRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_eat_ships_with_the_standard_default_rule(): void
    {
        $rule = BabyActionType::where('name', 'Eat')->firstOrFail()->notificationSettings()->sole();

        $this->assertTrue($rule->enabled);
        $this->assertSame(180, $rule->notify_after_minutes);
        $this->assertSame(NotifyFrom::StartedAt, $rule->notify_from);
        $this->assertSame('Time to eat!', $rule->title);
        $this->assertSame('Your baby is due for a feed.', $rule->description);
    }

    public function test_sleep_ships_with_a_60_minute_wake_up_rule(): void
    {
        $rule = BabyActionType::where('name', 'Sleep')->firstOrFail()->notificationSettings()->sole();

        $this->assertTrue($rule->enabled);
        $this->assertSame(60, $rule->notify_after_minutes);
        $this->assertSame(NotifyFrom::StartedAt, $rule->notify_from);
        $this->assertSame('Time to wake your baby up!', $rule->title);
        $this->assertSame("They've slept long enough.", $rule->description);
    }
}
