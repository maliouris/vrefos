<?php

namespace Tests\Feature;

use App\Enums\NotifyFrom;
use App\Livewire\Pages\NotificationSettings\Index;
use App\Models\Baby;
use App\Models\BabyAction;
use App\Models\BabyActionType;
use App\Models\NotificationSetting;
use App\Services\LocalNotificationScheduler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use ReflectionMethod;
use Tests\TestCase;

class NotificationSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function ruleFor(BabyActionType $type, array $overrides = []): NotificationSetting
    {
        return NotificationSetting::create(array_merge([
            'baby_action_type_id' => $type->id,
            'enabled' => true,
            'notify_after_minutes' => 180,
            'notify_from' => NotifyFrom::StartedAt,
        ], $overrides));
    }

    private function pendingActionFor(BabyActionType $type, Baby $baby): BabyAction
    {
        return BabyAction::factory()
            ->for($baby)
            ->create([
                'baby_action_type_id' => $type->id,
                'started_at' => now()->subHour(),
            ]);
    }

    private function resolveBody(BabyAction $action, NotificationSetting $rule): string
    {
        $method = new ReflectionMethod(LocalNotificationScheduler::class, 'resolveContent');
        $method->setAccessible(true);

        return $method->invoke(
            app(LocalNotificationScheduler::class),
            $action->fresh()->load(['baby', 'babyActionType']),
            $rule,
        )['body'];
    }

    public function test_save_rule_creates_rule_and_schedules(): void
    {
        $baby = Baby::factory()->create();
        $type = BabyActionType::factory()->create(['name' => 'Eat']);

        $action = $this->pendingActionFor($type, $baby);
        // No rules exist yet, so the action starts with nothing scheduled.
        $this->assertNull($action->fresh()->notification_scheduled_at);

        Livewire::test(Index::class)
            ->call('openCreate', $type->id)
            ->set('notifyAfterMinutes', 180)
            ->set('notifyFrom', 'started_at')
            ->call('saveRule')
            ->assertHasNoErrors()
            ->assertSet('showModal', false);

        $this->assertEquals(1, NotificationSetting::where('baby_action_type_id', $type->id)->count());
        $this->assertNotNull($action->fresh()->notification_scheduled_at);
    }

    public function test_second_save_rule_adds_a_second_rule(): void
    {
        $type = BabyActionType::factory()->create(['name' => 'Eat']);

        Livewire::test(Index::class)
            ->call('openCreate', $type->id)
            ->set('notifyAfterMinutes', 180)
            ->call('saveRule')
            ->assertHasNoErrors()
            ->call('openCreate', $type->id)
            ->set('notifyAfterMinutes', 240)
            ->call('saveRule')
            ->assertHasNoErrors();

        $this->assertEquals(2, NotificationSetting::where('baby_action_type_id', $type->id)->count());
    }

    public function test_toggle_enabled_off_cancels_pending(): void
    {
        $baby = Baby::factory()->create();
        $type = BabyActionType::factory()->create(['name' => 'Eat']);
        $rule = $this->ruleFor($type);

        $action = $this->pendingActionFor($type, $baby);
        $this->assertNotNull($action->fresh()->notification_scheduled_at);

        Livewire::test(Index::class)
            ->call('toggleEnabled', $rule->id)
            ->assertHasNoErrors();

        $this->assertFalse($rule->fresh()->enabled);
        $this->assertNull($action->fresh()->notification_scheduled_at);
    }

    public function test_delete_rule_cancels_its_notifications_and_keeps_siblings(): void
    {
        $baby = Baby::factory()->create();
        $type = BabyActionType::factory()->create(['name' => 'Eat']);
        $ruleKeep = $this->ruleFor($type, ['notify_after_minutes' => 180]);
        $ruleDelete = $this->ruleFor($type, ['notify_after_minutes' => 240]);

        $action = $this->pendingActionFor($type, $baby);
        $this->assertCount(2, $action->fresh()->scheduled_notification_keys);

        Livewire::test(Index::class)
            ->call('deleteRule', $ruleDelete->id)
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('notification_settings', ['id' => $ruleDelete->id]);
        $this->assertDatabaseHas('notification_settings', ['id' => $ruleKeep->id]);

        $keys = $action->fresh()->scheduled_notification_keys;
        $this->assertCount(1, $keys);
        $this->assertContains("action-{$action->id}-setting-{$ruleKeep->id}", $keys);
    }

    public function test_notify_after_minutes_must_be_within_bounds(): void
    {
        $type = BabyActionType::factory()->create(['name' => 'Eat']);

        Livewire::test(Index::class)
            ->call('openCreate', $type->id)
            ->set('notifyAfterMinutes', 0)
            ->call('saveRule')
            ->assertHasErrors('notifyAfterMinutes');
    }

    public function test_blank_message_falls_back_to_default_text(): void
    {
        $baby = Baby::factory()->create(['name' => 'Lily']);
        $type = BabyActionType::factory()->create(['name' => 'Eat']);
        $rule = $this->ruleFor($type, ['message' => null]);

        $action = $this->pendingActionFor($type, $baby);

        $this->assertEquals('Your baby needs eat.', $this->resolveBody($action, $rule));
    }

    public function test_message_placeholders_are_substituted(): void
    {
        $baby = Baby::factory()->create(['name' => 'Lily']);
        $type = BabyActionType::factory()->create(['name' => 'Eat']);
        $rule = $this->ruleFor($type, [
            'notify_after_minutes' => 120,
            'message' => '#{baby} needs #{action} in #{minutes} min',
        ]);

        $action = $this->pendingActionFor($type, $baby);

        $this->assertEquals('Lily needs eat in 120 min', $this->resolveBody($action, $rule));
    }
}
