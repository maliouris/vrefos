<?php

namespace Tests\Feature\Api\V1;

use App\Models\Baby;
use App\Models\BabyAction;
use App\Models\BabyActionType;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SyncControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    // ─── Auth ───────────────────────────────────────────────────────────────────

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->postJson('/api/v1/sync', [])->assertUnauthorized();
    }

    // ─── Babies ─────────────────────────────────────────────────────────────────

    public function test_creates_new_baby_from_sync_payload(): void
    {
        $uuid = (string) Str::uuid();

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/sync', [
                'babies' => [[
                    'uuid' => $uuid,
                    'name' => 'Lena',
                    'birth_date' => '2024-01-15',
                    'gender' => 'female',
                    'updated_at' => now()->toIso8601String(),
                ]],
            ])
            ->assertOk()
            ->assertJson(['synced' => true]);

        $this->assertDatabaseHas('babies', [
            'uuid' => $uuid,
            'name' => 'Lena',
            'user_id' => $this->user->id,
        ]);
    }

    public function test_updates_existing_baby_on_sync(): void
    {
        $baby = Baby::factory()->create(['user_id' => $this->user->id, 'name' => 'Old Name', 'gender' => 'female']);

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/sync', [
                'babies' => [[
                    'uuid' => $baby->uuid,
                    'name' => 'New Name',
                    'birth_date' => $baby->birth_date->toDateString(),
                    'gender' => 'female',
                    'updated_at' => now()->toIso8601String(),
                ]],
            ])
            ->assertOk();

        $this->assertDatabaseHas('babies', ['uuid' => $baby->uuid, 'name' => 'New Name']);
    }

    public function test_does_not_overwrite_another_users_baby(): void
    {
        $otherUser = User::factory()->create();
        $baby = Baby::factory()->create(['user_id' => $otherUser->id, 'name' => 'Original', 'gender' => 'male']);

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/sync', [
                'babies' => [[
                    'uuid' => $baby->uuid,
                    'name' => 'Hijacked',
                    'birth_date' => $baby->birth_date->toDateString(),
                    'gender' => 'male',
                    'updated_at' => now()->toIso8601String(),
                ]],
            ])
            ->assertOk();

        $this->assertDatabaseHas('babies', ['uuid' => $baby->uuid, 'name' => 'Original']);
    }

    // ─── Baby Actions ────────────────────────────────────────────────────────────

    public function test_creates_baby_action_with_resolved_baby_uuid(): void
    {
        $baby = Baby::factory()->create(['user_id' => $this->user->id]);
        $actionType = BabyActionType::first();
        $actionUuid = (string) Str::uuid();

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/sync', [
                'baby_actions' => [[
                    'uuid' => $actionUuid,
                    'baby_uuid' => $baby->uuid,
                    'baby_action_type_id' => $actionType->id,
                    'started_at' => now()->subHours(2)->toIso8601String(),
                    'finished_at' => now()->subHour()->toIso8601String(),
                    'reminders' => 0,
                    'updated_at' => now()->toIso8601String(),
                ]],
            ])
            ->assertOk();

        $this->assertDatabaseHas('baby_actions', [
            'uuid' => $actionUuid,
            'baby_id' => $baby->id,
        ]);
    }

    public function test_skips_baby_action_when_baby_uuid_not_found(): void
    {
        $actionType = BabyActionType::first();

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/sync', [
                'baby_actions' => [[
                    'uuid' => (string) Str::uuid(),
                    'baby_uuid' => (string) Str::uuid(), // non-existent baby
                    'baby_action_type_id' => $actionType->id,
                    'started_at' => now()->toIso8601String(),
                    'finished_at' => null,
                    'reminders' => 0,
                    'updated_at' => now()->toIso8601String(),
                ]],
            ])
            ->assertOk();

        $this->assertDatabaseCount('baby_actions', 0);
    }

    // ─── Eat Details ─────────────────────────────────────────────────────────────

    public function test_creates_eat_detail_with_resolved_action_uuid(): void
    {
        $baby = Baby::factory()->create(['user_id' => $this->user->id]);
        $action = BabyAction::factory()->create(['baby_id' => $baby->id]);
        $detailUuid = (string) Str::uuid();

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/sync', [
                'baby_action_eat_details' => [[
                    'uuid' => $detailUuid,
                    'baby_action_uuid' => $action->uuid,
                    'food_type' => 'breast_milk',
                    'breast_side' => 'left',
                    'updated_at' => now()->toIso8601String(),
                ]],
            ])
            ->assertOk();

        $this->assertDatabaseHas('baby_action_eat_details', [
            'uuid' => $detailUuid,
            'baby_action_id' => $action->id,
            'food_type' => 'breast_milk',
            'breast_side' => 'left',
        ]);
    }

    // ─── Notification Settings ───────────────────────────────────────────────────

    public function test_creates_notification_setting(): void
    {
        $actionType = BabyActionType::first();
        $settingUuid = (string) Str::uuid();

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/sync', [
                'notification_settings' => [[
                    'uuid' => $settingUuid,
                    'baby_action_type_id' => $actionType->id,
                    'enabled' => true,
                    'notify_after_minutes' => 120,
                    'notify_from' => 'started_at',
                    'updated_at' => now()->toIso8601String(),
                ]],
            ])
            ->assertOk();

        $this->assertDatabaseHas('notification_settings', [
            'uuid' => $settingUuid,
            'user_id' => $this->user->id,
            'notify_after_minutes' => 120,
        ]);
    }

    // ─── Validation ──────────────────────────────────────────────────────────────

    public function test_validates_required_fields_on_babies(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/sync', [
                'babies' => [['uuid' => 'not-a-uuid']],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['babies.0.uuid', 'babies.0.name', 'babies.0.birth_date']);
    }

    public function test_empty_payload_succeeds(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/sync', [])
            ->assertOk()
            ->assertJson(['synced' => true]);
    }
}
