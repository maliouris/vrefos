<?php

namespace App\Services;

use App\Models\Baby;
use App\Models\BabyAction;
use App\Models\BabyActionEatDetail;
use App\Models\NotificationSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Native\Mobile\Facades\Network;
use Native\Mobile\Facades\SecureStorage;

class SyncService
{
    private const TOKEN_KEY = 'server_api_token';

    /** @var Baby[] */
    private array $dirtyBabies = [];

    /** @var BabyAction[] */
    private array $dirtyActions = [];

    /** @var BabyActionEatDetail[] */
    private array $dirtyEatDetails = [];

    /** @var NotificationSetting[] */
    private array $dirtySettings = [];

    /**
     * Sync all dirty records to the remote server.
     *
     * Silently no-ops when:
     *  - not running inside NativePHP
     *  - no network connectivity
     *  - no server token stored yet
     */
    public function sync(): void
    {
        if (! function_exists('nativephp_call')) {
            return;
        }

        if (! Network::status()->connected) {
            return;
        }

        $token = SecureStorage::get(self::TOKEN_KEY);

        if (empty($token)) {
            return;
        }

        $payload = $this->collectDirtyRecords();

        if ($this->isPayloadEmpty($payload)) {
            return;
        }

        $serverUrl = rtrim(config('services.sync_server.url', ''), '/');

        $response = Http::withToken($token)
            ->timeout(30)
            ->connectTimeout(10)
            ->retry(2, 500)
            ->post("{$serverUrl}/api/v1/sync", $payload);

        if ($response->successful()) {
            $this->markAllSynced();
        } else {
            Log::warning('SyncService: sync failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        }
    }

    /**
     * Store or clear the Sanctum token used for sync.
     */
    public function storeToken(string $token): void
    {
        SecureStorage::set(self::TOKEN_KEY, $token);
    }

    public function clearToken(): void
    {
        SecureStorage::delete(self::TOKEN_KEY);
    }

    public function hasToken(): bool
    {
        return ! empty(SecureStorage::get(self::TOKEN_KEY));
    }

    /**
     * @return array{
     *     babies: array<int, array<string, mixed>>,
     *     baby_actions: array<int, array<string, mixed>>,
     *     baby_action_eat_details: array<int, array<string, mixed>>,
     *     notification_settings: array<int, array<string, mixed>>,
     * }
     */
    private function collectDirtyRecords(): array
    {
        $user = auth()->user();

        $this->dirtyBabies = Baby::where('user_id', $user->id)
            ->dirty()
            ->get()
            ->all();

        $this->dirtyActions = BabyAction::whereHas('baby', fn ($q) => $q->where('user_id', $user->id))
            ->with('baby')
            ->dirty()
            ->get()
            ->all();

        $this->dirtyEatDetails = BabyActionEatDetail::whereHas('babyAction.baby', fn ($q) => $q->where('user_id', $user->id))
            ->with('babyAction')
            ->dirty()
            ->get()
            ->all();

        $this->dirtySettings = NotificationSetting::where('user_id', $user->id)
            ->dirty()
            ->get()
            ->all();

        return [
            'babies' => collect($this->dirtyBabies)->map(fn (Baby $b) => [
                'uuid' => $b->uuid,
                'name' => $b->name,
                'birth_date' => $b->birth_date?->toDateString(),
                'gender' => $b->gender?->value,
                'updated_at' => $b->updated_at?->toIso8601String(),
            ])->values()->all(),

            'baby_actions' => collect($this->dirtyActions)->map(fn (BabyAction $a) => [
                'uuid' => $a->uuid,
                'baby_uuid' => $a->baby->uuid,
                'baby_action_type_id' => $a->baby_action_type_id,
                'started_at' => $a->started_at?->toIso8601String(),
                'finished_at' => $a->finished_at?->toIso8601String(),
                'reminders' => $a->reminders ?? 0,
                'updated_at' => $a->updated_at?->toIso8601String(),
            ])->values()->all(),

            'baby_action_eat_details' => collect($this->dirtyEatDetails)->map(fn (BabyActionEatDetail $d) => [
                'uuid' => $d->uuid,
                'baby_action_uuid' => $d->babyAction->uuid,
                'food_type' => $d->food_type?->value,
                'breast_side' => $d->breast_side?->value,
                'updated_at' => $d->updated_at?->toIso8601String(),
            ])->values()->all(),

            'notification_settings' => collect($this->dirtySettings)->map(fn (NotificationSetting $s) => [
                'uuid' => $s->uuid,
                'baby_action_type_id' => $s->baby_action_type_id,
                'enabled' => $s->enabled,
                'notify_after_minutes' => $s->notify_after_minutes,
                'notify_from' => $s->notify_from?->value,
                'updated_at' => $s->updated_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }

    private function isPayloadEmpty(array $payload): bool
    {
        return collect($payload)->every(fn (array $records) => empty($records));
    }

    private function markAllSynced(): void
    {
        foreach ($this->dirtyBabies as $baby) {
            $baby->markSynced();
        }

        foreach ($this->dirtyActions as $action) {
            $action->markSynced();
        }

        foreach ($this->dirtyEatDetails as $detail) {
            $detail->markSynced();
        }

        foreach ($this->dirtySettings as $setting) {
            $setting->markSynced();
        }
    }
}
