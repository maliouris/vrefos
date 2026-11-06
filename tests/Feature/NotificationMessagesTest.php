<?php

namespace Tests\Feature;

use App\Livewire\Pages\Notification\Index;
use App\Models\BabyActionType;
use App\Models\Notification;
use App\Models\Plan;
use App\Models\PlanVersion;
use App\Services\PlanScheduler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class NotificationMessagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_a_message(): void
    {
        Livewire::test(Index::class)
            ->set('title', 'Time to eat, #{baby}!')
            ->set('description', 'Due #{interval} after last meal')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('notifications', [
            'title' => 'Time to eat, #{baby}!',
            'description' => 'Due #{interval} after last meal',
        ]);
    }

    public function test_title_is_required(): void
    {
        Livewire::test(Index::class)
            ->set('title', '')
            ->call('save')
            ->assertHasErrors(['title' => 'required']);
    }

    public function test_can_edit_a_message(): void
    {
        $notification = Notification::factory()->create(['title' => 'Old title']);

        Livewire::test(Index::class)
            ->call('openEdit', $notification->id)
            ->set('title', 'New title')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('New title', $notification->fresh()->title);
    }

    public function test_editing_a_message_reschedules_attached_plans(): void
    {
        $notification = Notification::factory()->create();
        $type = BabyActionType::factory()->create();
        $plan = Plan::factory()->create(['baby_action_type_id' => $type->id]);
        PlanVersion::factory()->for($plan)->create();
        $plan->startNotifications()->attach($notification, ['trigger' => 'start']);

        $scheduler = $this->spy(PlanScheduler::class);

        Livewire::test(Index::class)
            ->call('openEdit', $notification->id)
            ->set('title', 'Updated!')
            ->call('save');

        $scheduler->shouldHaveReceived('rescheduleFor')->once();
    }

    public function test_deleting_a_message_detaches_from_plans_and_reschedules(): void
    {
        $notification = Notification::factory()->create();
        $type = BabyActionType::factory()->create();
        $plan = Plan::factory()->create(['baby_action_type_id' => $type->id]);
        PlanVersion::factory()->for($plan)->create();
        $plan->startNotifications()->attach($notification, ['trigger' => 'start']);

        $scheduler = $this->spy(PlanScheduler::class);

        Livewire::test(Index::class)
            ->call('promptDelete', $notification->id)
            ->call('confirmDelete');

        $this->assertDatabaseMissing('notifications', ['id' => $notification->id]);
        $this->assertDatabaseMissing('notification_plan', ['notification_id' => $notification->id]);
        $scheduler->shouldHaveReceived('rescheduleFor')->once();
    }
}
