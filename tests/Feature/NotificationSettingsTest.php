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
            'title' => 'Time to check on your baby!',
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

    public function test_prompt_delete_rule_opens_modal(): void
    {
        $type = BabyActionType::factory()->create(['name' => 'Eat']);
        $rule = $this->ruleFor($type);

        Livewire::test(Index::class)
            ->call('promptDeleteRule', $rule->id)
            ->assertSet('deletingRuleId', $rule->id);
    }

    public function test_prompt_delete_rule_closes_edit_modal(): void
    {
        $type = BabyActionType::factory()->create(['name' => 'Eat']);
        $rule = $this->ruleFor($type);

        Livewire::test(Index::class)
            ->call('openEdit', $rule->id)
            ->assertSet('showModal', true)
            ->call('promptDeleteRule', $rule->id)
            ->assertSet('showModal', false)
            ->assertSet('deletingRuleId', $rule->id);
    }

    public function test_row_navigates_to_edit_and_has_no_inline_action_buttons(): void
    {
        $type = BabyActionType::factory()->create(['name' => 'Eat']);
        $rule = $this->ruleFor($type);

        Livewire::test(Index::class)
            ->assertSeeHtml("openEdit({$rule->id})")
            ->assertDontSeeHtml("promptDeleteRule({$rule->id})");
    }

    public function test_close_delete_modal_closes_modal(): void
    {
        $type = BabyActionType::factory()->create(['name' => 'Eat']);
        $rule = $this->ruleFor($type);

        Livewire::test(Index::class)
            ->call('promptDeleteRule', $rule->id)
            ->assertSet('deletingRuleId', $rule->id)
            ->call('closeDeleteModal')
            ->assertSet('deletingRuleId', null);
    }

    public function test_delete_rule_closes_modal_after_deleting(): void
    {
        $type = BabyActionType::factory()->create(['name' => 'Eat']);
        $rule = $this->ruleFor($type);

        Livewire::test(Index::class)
            ->call('promptDeleteRule', $rule->id)
            ->call('deleteRule', $rule->id)
            ->assertSet('deletingRuleId', null)
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('notification_settings', ['id' => $rule->id]);
    }

    public function test_save_rule_with_all_children_attaches_every_baby(): void
    {
        $babyA = Baby::factory()->create();
        $babyB = Baby::factory()->create();
        $type = BabyActionType::factory()->create(['name' => 'Eat']);

        Livewire::test(Index::class)
            ->call('openCreate', $type->id)
            ->assertSet('allChildren', true)
            ->call('saveRule')
            ->assertHasNoErrors();

        $rule = NotificationSetting::where('baby_action_type_id', $type->id)->firstOrFail();
        $this->assertTrue($rule->all_children);
        $this->assertEqualsCanonicalizing(
            [$babyA->id, $babyB->id],
            $rule->babies()->pluck('babies.id')->all(),
        );
    }

    public function test_save_rule_with_specific_babies_attaches_only_those(): void
    {
        $babyA = Baby::factory()->create();
        $babyB = Baby::factory()->create();
        $type = BabyActionType::factory()->create(['name' => 'Eat']);

        Livewire::test(Index::class)
            ->call('openCreate', $type->id)
            ->call('toggleBaby', $babyA->id)
            ->assertSet('allChildren', false)
            ->call('saveRule')
            ->assertHasNoErrors();

        $rule = NotificationSetting::where('baby_action_type_id', $type->id)->firstOrFail();
        $this->assertFalse($rule->all_children);
        $this->assertEquals([$babyA->id], $rule->babies()->pluck('babies.id')->all());
    }

    public function test_open_edit_rehydrates_targeting(): void
    {
        $babyA = Baby::factory()->create();
        Baby::factory()->create();
        $type = BabyActionType::factory()->create(['name' => 'Eat']);
        $rule = $this->ruleFor($type, ['all_children' => false]);
        $rule->babies()->attach($babyA->id);

        Livewire::test(Index::class)
            ->call('openEdit', $rule->id)
            ->assertSet('allChildren', false)
            ->assertSet('targetBabyIds', [$babyA->id]);
    }

    public function test_toggle_baby_clears_all_and_clearing_last_reverts_to_all(): void
    {
        $baby = Baby::factory()->create();
        $type = BabyActionType::factory()->create(['name' => 'Eat']);

        Livewire::test(Index::class)
            ->call('openCreate', $type->id)
            ->assertSet('allChildren', true)
            ->call('toggleBaby', $baby->id)
            ->assertSet('allChildren', false)
            ->assertSet('targetBabyIds', [$baby->id])
            ->call('toggleBaby', $baby->id)
            ->assertSet('allChildren', true)
            ->assertSet('targetBabyIds', []);
    }

    public function test_specific_targeting_requires_at_least_one_baby(): void
    {
        Baby::factory()->create();
        $type = BabyActionType::factory()->create(['name' => 'Eat']);

        Livewire::test(Index::class)
            ->call('openCreate', $type->id)
            ->set('allChildren', false)
            ->set('targetBabyIds', [])
            ->call('saveRule')
            ->assertHasErrors('targetBabyIds');
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

    public function test_blank_description_yields_empty_body(): void
    {
        $baby = Baby::factory()->create(['name' => 'Lily']);
        $type = BabyActionType::factory()->create(['name' => 'Eat']);
        $rule = $this->ruleFor($type, ['description' => null]);

        $action = $this->pendingActionFor($type, $baby);

        $this->assertSame('', $this->resolveBody($action, $rule));
    }

    public function test_description_placeholders_are_substituted(): void
    {
        $baby = Baby::factory()->create(['name' => 'Lily']);
        $type = BabyActionType::factory()->create(['name' => 'Eat']);
        $rule = $this->ruleFor($type, [
            'notify_after_minutes' => 120,
            'description' => '#{baby} needs #{action} in #{minutes} min',
        ]);

        $action = $this->pendingActionFor($type, $baby);

        $this->assertEquals('Lily needs eat in 120 min', $this->resolveBody($action, $rule));
    }

    public function test_open_create_prefills_title_from_type(): void
    {
        $type = BabyActionType::factory()->create(['name' => 'Eat']);

        Livewire::test(Index::class)
            ->call('openCreate', $type->id)
            ->assertSet('title', 'Time to eat!');
    }

    public function test_title_is_required(): void
    {
        $type = BabyActionType::factory()->create(['name' => 'Eat']);

        Livewire::test(Index::class)
            ->call('openCreate', $type->id)
            ->set('title', '')
            ->call('saveRule')
            ->assertHasErrors('title');
    }

    public function test_save_rule_persists_title_and_description(): void
    {
        $type = BabyActionType::factory()->create(['name' => 'Eat']);

        Livewire::test(Index::class)
            ->call('openCreate', $type->id)
            ->set('title', 'Feed me')
            ->set('description', 'Right now please')
            ->call('saveRule')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('notification_settings', [
            'baby_action_type_id' => $type->id,
            'title' => 'Feed me',
            'description' => 'Right now please',
        ]);
    }
}
