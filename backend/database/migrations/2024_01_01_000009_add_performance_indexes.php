<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!$this->indexExists('incident_views', 'incident_views_user_id_index')) {
            Schema::table('incident_views', function (Blueprint $table) {
                $table->index('user_id', 'incident_views_user_id_index');
            });
        }

        if (!$this->indexExists('incident_logs', 'incident_logs_incident_id_index')) {
            Schema::table('incident_logs', function (Blueprint $table) {
                $table->index('incident_id', 'incident_logs_incident_id_index');
            });
        }

        if (!$this->indexExists('logs', 'logs_created_at_index')) {
            Schema::table('logs', function (Blueprint $table) {
                $table->index('created_at', 'logs_created_at_index');
            });
        }
    }

    public function down(): void
    {
        if ($this->indexExists('incident_views', 'incident_views_user_id_index')) {
            Schema::table('incident_views', function (Blueprint $table) {
                $table->dropIndex('incident_views_user_id_index');
            });
        }

        if ($this->indexExists('incident_logs', 'incident_logs_incident_id_index')) {
            Schema::table('incident_logs', function (Blueprint $table) {
                $table->dropIndex('incident_logs_incident_id_index');
            });
        }

        if ($this->indexExists('logs', 'logs_created_at_index')) {
            Schema::table('logs', function (Blueprint $table) {
                $table->dropIndex('logs_created_at_index');
            });
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        $result = DB::select("SELECT 1 FROM pg_indexes WHERE tablename = ? AND indexname = ?", [$table, $index]);
        return count($result) > 0;
    }
};