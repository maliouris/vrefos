<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add the title as nullable first so existing rows can be backfilled.
        Schema::table('notification_settings', function (Blueprint $table) {
            $table->string('title')->nullable()->after('notify_from');
        });

        // Mirror the old hardcoded auto-title ("Time to {action}!") onto every
        // existing rule so user-created rules keep a sensible title.
        foreach (DB::table('notification_settings')->get() as $row) {
            $typeName = DB::table('baby_action_types')
                ->where('id', $row->baby_action_type_id)
                ->value('name');

            DB::table('notification_settings')
                ->where('id', $row->id)
                ->update(['title' => 'Time to '.strtolower((string) $typeName).'!']);
        }

        // Now that every row has a title, enforce it at the database level and
        // repurpose the optional `message` body as `description`.
        Schema::table('notification_settings', function (Blueprint $table) {
            $table->string('title')->nullable(false)->change();
            $table->renameColumn('message', 'description');
        });
    }

    public function down(): void
    {
        Schema::table('notification_settings', function (Blueprint $table) {
            $table->renameColumn('description', 'message');
            $table->dropColumn('title');
        });
    }
};
