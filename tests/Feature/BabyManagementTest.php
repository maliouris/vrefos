<?php

namespace Tests\Feature;

use App\Enums\NotifyFrom;
use App\Livewire\Pages\Baby\Create;
use App\Livewire\Pages\Baby\Edit;
use App\Livewire\Pages\Baby\Index;
use App\Models\Baby;
use App\Models\BabyAction;
use App\Models\BabyActionType;
use App\Models\NotificationSetting;
use App\Services\LocalNotificationScheduler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BabyManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_persists_baby_and_redirects(): void
    {
        Livewire::test(Create::class)
            ->set('name', 'Lily')
            ->set('birth_date', '2026-01-15')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('babies.show'));

        $this->assertDatabaseHas('babies', ['name' => 'Lily']);
    }

    public function test_create_requires_name_but_not_birth_date(): void
    {
        Livewire::test(Create::class)
            ->call('save')
            ->assertHasErrors(['name'])
            ->assertHasNoErrors(['birth_date']);
    }

    public function test_create_persists_baby_without_birth_date(): void
    {
        Livewire::test(Create::class)
            ->set('name', 'Lily')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('babies.show'));

        $this->assertDatabaseHas('babies', ['name' => 'Lily', 'birth_date' => null]);
    }

    public function test_edit_updates_existing_baby(): void
    {
        $baby = Baby::factory()->create(['name' => 'Old']);

        Livewire::test(Edit::class, ['baby' => $baby])
            ->assertSet('name', 'Old')
            ->set('name', 'New')
            ->call('update')
            ->assertHasNoErrors();

        $this->assertEquals('New', $baby->fresh()->name);
    }

    public function test_edit_handles_baby_without_birth_date(): void
    {
        $baby = Baby::factory()->create(['name' => 'Old', 'birth_date' => null]);

        Livewire::test(Edit::class, ['baby' => $baby])
            ->assertSet('birth_date', '')
            ->set('name', 'New')
            ->call('update')
            ->assertHasNoErrors();

        $baby->refresh();
        $this->assertEquals('New', $baby->name);
        $this->assertNull($baby->birth_date);
    }

    public function test_delete_removes_baby_and_its_actions_and_cancels_notifications(): void
    {
        $baby = Baby::factory()->create();
        $type = BabyActionType::factory()->create(['name' => 'Eat']);

        NotificationSetting::create([
            'baby_action_type_id' => $type->id,
            'enabled' => true,
            'notify_after_minutes' => 180,
            'notify_from' => NotifyFrom::StartedAt,
            'title' => 'Time to eat!',
        ]);

        $action = BabyAction::factory()->for($baby)->create([
            'baby_action_type_id' => $type->id,
            'started_at' => now()->subHour(),
            'finished_at' => null,
        ]);

        $this->assertNotNull($action->fresh()->notification_scheduled_at);

        $scheduler = $this->spy(LocalNotificationScheduler::class);

        Livewire::test(Index::class)
            ->call('delete', $baby->id)
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('babies', ['id' => $baby->id]);
        $this->assertDatabaseMissing('baby_actions', ['id' => $action->id]);

        // Proves the actions were deleted through Eloquent (observer path),
        // not the DB cascade, which would leave OS notifications scheduled.
        $scheduler->shouldHaveReceived('cancelFor')->once();
    }

    public function test_edit_can_clear_birth_date(): void
    {
        $baby = Baby::factory()->create();

        Livewire::test(Edit::class, ['baby' => $baby])
            ->set('birth_date', '')
            ->call('update')
            ->assertHasNoErrors();

        $this->assertNull($baby->fresh()->birth_date);
    }
}
