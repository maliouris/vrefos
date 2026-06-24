<?php

use App\Enums\NotifyFrom;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Eat keeps the standard default reminder: 180 minutes from the start time.
        $this->applyRule('Eat', [
            'enabled' => true,
            'notify_after_minutes' => 180,
            'notify_from' => NotifyFrom::StartedAt->value,
            'message' => null,
        ]);

        // Sleep wakes the baby 60 minutes after sleep starts. The default body text
        // ("Your baby needs sleep.") would be wrong here, so a wake-up message is set.
        $this->applyRule('Sleep', [
            'enabled' => true,
            'notify_after_minutes' => 60,
            'notify_from' => NotifyFrom::StartedAt->value,
            'message' => 'Time to wake your baby up!',
        ]);
    }

    /**
     * Reverse the migrations: restore both types to the generic default rule.
     */
    public function down(): void
    {
        foreach (['Eat', 'Sleep'] as $name) {
            $this->applyRule($name, [
                'enabled' => true,
                'notify_after_minutes' => 180,
                'notify_from' => NotifyFrom::StartedAt->value,
                'message' => null,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function applyRule(string $typeName, array $values): void
    {
        $typeId = DB::table('baby_action_types')->where('name', $typeName)->value('id');

        if ($typeId === null) {
            return;
        }

        $query = DB::table('notification_settings')->where('baby_action_type_id', $typeId);

        if ($query->exists()) {
            $query->update($values + ['updated_at' => now()]);
        } else {
            DB::table('notification_settings')->insert(
                $values + ['baby_action_type_id' => $typeId, 'created_at' => now(), 'updated_at' => now()],
            );
        }
    }
};
