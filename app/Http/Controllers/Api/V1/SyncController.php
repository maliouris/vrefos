<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SyncRequest;
use App\Models\Baby;
use App\Models\BabyAction;
use App\Models\BabyActionEatDetail;
use App\Models\NotificationSetting;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class SyncController extends Controller
{
    /**
     * Bulk upsert all dirty records from the mobile device.
     */
    public function store(SyncRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        DB::transaction(function () use ($request, $user): void {
            $this->syncBabies($user, $request->validated('babies', []));
            $this->syncBabyActions($user, $request->validated('baby_actions', []));
            $this->syncBabyActionEatDetails($user, $request->validated('baby_action_eat_details', []));
            $this->syncNotificationSettings($user, $request->validated('notification_settings', []));
        });

        return response()->json(['synced' => true]);
    }

    private function syncBabies(User $user, array $babies): void
    {
        foreach ($babies as $data) {
            $baby = Baby::firstOrNew(['uuid' => $data['uuid']]);

            if ($baby->exists && $baby->user_id !== $user->id) {
                continue;
            }

            $baby->fill(array_filter([
                'user_id' => $user->id,
                'name' => $data['name'],
                'birth_date' => $data['birth_date'],
                'gender' => $data['gender'] ?? null,
            ], fn ($v) => $v !== null))->save();
        }
    }

    private function syncBabyActions(User $user, array $actions): void
    {
        // Build a baby UUID → ID map scoped to this user for FK resolution
        $babyUuids = collect($actions)->pluck('baby_uuid')->unique()->all();
        $babyIdMap = Baby::where('user_id', $user->id)
            ->whereIn('uuid', $babyUuids)
            ->pluck('id', 'uuid');

        foreach ($actions as $data) {
            $babyId = $babyIdMap[$data['baby_uuid']] ?? null;

            if ($babyId === null) {
                continue; // Referenced baby hasn't synced yet or doesn't belong to user
            }

            $action = BabyAction::firstOrNew(['uuid' => $data['uuid']]);

            // Verify ownership via the baby
            if ($action->exists && $action->baby_id !== $babyId) {
                continue;
            }

            $action->fill([
                'baby_id' => $babyId,
                'baby_action_type_id' => $data['baby_action_type_id'],
                'started_at' => $data['started_at'],
                'finished_at' => $data['finished_at'] ?? null,
                'reminders' => $data['reminders'],
            ])->save();
        }
    }

    private function syncBabyActionEatDetails(User $user, array $details): void
    {
        // Build an action UUID → ID map scoped to this user
        $actionUuids = collect($details)->pluck('baby_action_uuid')->unique()->all();
        $actionIdMap = BabyAction::whereIn('uuid', $actionUuids)
            ->whereHas('baby', fn ($q) => $q->where('user_id', $user->id))
            ->pluck('id', 'uuid');

        foreach ($details as $data) {
            $actionId = $actionIdMap[$data['baby_action_uuid']] ?? null;

            if ($actionId === null) {
                continue;
            }

            $detail = BabyActionEatDetail::firstOrNew(['uuid' => $data['uuid']]);

            if ($detail->exists && $detail->baby_action_id !== $actionId) {
                continue;
            }

            $detail->fill([
                'baby_action_id' => $actionId,
                'food_type' => $data['food_type'] ?? null,
                'breast_side' => $data['breast_side'] ?? null,
            ])->save();
        }
    }

    private function syncNotificationSettings(User $user, array $settings): void
    {
        foreach ($settings as $data) {
            $setting = NotificationSetting::firstOrNew(['uuid' => $data['uuid']]);

            if ($setting->exists && $setting->user_id !== $user->id) {
                continue;
            }

            $setting->fill([
                'user_id' => $user->id,
                'baby_action_type_id' => $data['baby_action_type_id'],
                'enabled' => $data['enabled'],
                'notify_after_minutes' => $data['notify_after_minutes'],
                'notify_from' => $data['notify_from'],
            ])->save();
        }
    }
}
