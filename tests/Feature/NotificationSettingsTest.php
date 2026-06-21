<?php

namespace Tests\Feature;

use App\Enums\NotifyFrom;
use App\Livewire\Pages\NotificationSettings\Index;
use App\Models\Baby;
use App\Models\BabyAction;
use App\Models\BabyActionType;
use App\Models\NotificationSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
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

    public function test_saving_unchanged_settings_does_not_trigger_reschedule(): void
    {
        $actionType = BabyActionType::factory()->create(['name' => 'Eat']);
        $action = $this->pendingActionFor($actionType);
        $scheduledAt = $action->notification_scheduled_at;

        // Mount populates settings from the defaults, so saving without
        // changing anything must be a no-op. Regression guard: form values
        // arrive as strings; a strict !== against the int cast would always
        // report "changed" and reschedule every action needlessly.
        $this->travel(2)->minutes();

        Livewire::test(Index::class)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertEquals(
            $scheduledAt->toDateTimeString(),
            $action->fresh()->notification_scheduled_at->toDateTimeString(),
            'Unchanged settings must not reschedule pending notifications.'
        );
    }

    public function test_changing_notify_after_minutes_reschedules_for_type(): void
    {
        $actionType = BabyActionType::factory()->create(['name' => 'Eat']);
        $action = $this->pendingActionFor($actionType);
        $scheduledAt = $action->notification_scheduled_at;

        $this->travel(2)->minutes();

        Livewire::test(Index::class)
            ->set("settings.{$actionType->id}.notify_after_minutes", 240)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertEquals(240, NotificationSetting::firstWhere('baby_action_type_id', $actionType->id)->notify_after_minutes);
        $this->assertTrue(
            $action->fresh()->notification_scheduled_at->greaterThan($scheduledAt),
            'Changing notify_after_minutes should reschedule pending notifications.'
        );
    }

    public function test_disabling_a_setting_cancels_for_type(): void
    {
        $actionType = BabyActionType::factory()->create(['name' => 'Eat']);
        $action = $this->pendingActionFor($actionType);

        Livewire::test(Index::class)
            ->set("settings.{$actionType->id}.enabled", false)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertFalse(NotificationSetting::firstWhere('baby_action_type_id', $actionType->id)->enabled);
        $this->assertNull(
            $action->fresh()->notification_scheduled_at,
            'Disabling a setting should cancel pending notifications.'
        );
    }

    private function pendingActionFor(BabyActionType $actionType): BabyAction
    {
        $action = BabyAction::factory()
            ->for(Baby::factory())
            ->create([
                'baby_action_type_id' => $actionType->id,
                'started_at' => now()->subHour(),
            ]);

        $this->assertNotNull($action->notification_scheduled_at);

        return $action;
    }

    public function test_notify_after_minutes_must_be_within_bounds(): void
    {
        $actionType = BabyActionType::factory()->create(['name' => 'Eat']);

        Livewire::test(Index::class)
            ->set("settings.{$actionType->id}.notify_after_minutes", 0)
            ->call('save')
            ->assertHasErrors("settings.{$actionType->id}.notify_after_minutes");
    }
}
