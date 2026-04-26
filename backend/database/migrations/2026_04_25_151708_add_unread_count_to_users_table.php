<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('unread_count')->default(0);
        });

        DB::statement('
            UPDATE users SET unread_count = (
                SELECT COUNT(*) FROM incidents
                WHERE NOT EXISTS (
                    SELECT 1 FROM incident_views
                    WHERE incident_views.incident_id = incidents.id
                    AND incident_views.user_id = users.id
                )
            )
        ');
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('unread_count');
        });
    }
};
