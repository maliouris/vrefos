<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Give the seeded Eat/Sleep rules clean title + description copy now that the
     * title is editable. Updates conservatively — only rows that still match the
     * old seeded defaults — so user-edited or extra rules are left untouched.
     */
    public function up(): void
    {
        $this->updateDefault('Eat', function ($query) {
            $query->where(function ($q) {
                $q->whereNull('description')->orWhere('description', '');
            })->update([
                'title' => 'Time to eat!',
                'description' => 'Your baby is due for a feed.',
                'updated_at' => now(),
            ]);
        });

        $this->updateDefault('Sleep', function ($query) {
            $query->where('description', 'Time to wake your baby up!')->update([
                'title' => 'Time to wake your baby up!',
                'description' => "They've slept long enough.",
                'updated_at' => now(),
            ]);
        });
    }

    /**
     * Restore the texts the rows carried before this migration (the title the
     * previous migration backfilled, and the original message/description).
     */
    public function down(): void
    {
        $this->updateDefault('Eat', function ($query) {
            $query->where('description', 'Your baby is due for a feed.')->update([
                'title' => 'Time to eat!',
                'description' => null,
                'updated_at' => now(),
            ]);
        });

        $this->updateDefault('Sleep', function ($query) {
            $query->where('description', "They've slept long enough.")->update([
                'title' => 'Time to sleep!',
                'description' => 'Time to wake your baby up!',
                'updated_at' => now(),
            ]);
        });
    }

    /**
     * @param  callable(Builder): void  $apply
     */
    private function updateDefault(string $typeName, callable $apply): void
    {
        $typeId = DB::table('baby_action_types')->where('name', $typeName)->value('id');

        if ($typeId === null) {
            return;
        }

        $apply(DB::table('notification_settings')->where('baby_action_type_id', $typeId));
    }
};
