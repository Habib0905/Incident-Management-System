<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!$this->indexExists('incidents', 'incidents_type_idx')) {
            Schema::table('incidents', function (Blueprint $table) {
                $table->index('type', 'incidents_type_idx');
            });
        }

        if (!$this->indexExists('incidents', 'incidents_status_idx')) {
            Schema::table('incidents', function (Blueprint $table) {
                $table->index('status', 'incidents_status_idx');
            });
        }

        if (!$this->indexExists('incidents', 'incidents_severity_idx')) {
            Schema::table('incidents', function (Blueprint $table) {
                $table->index('severity', 'incidents_severity_idx');
            });
        }

        if (!$this->indexExists('incidents', 'incidents_created_at_idx')) {
            Schema::table('incidents', function (Blueprint $table) {
                $table->index('created_at', 'incidents_created_at_idx');
            });
        }

        if (!$this->indexExists('incident_views', 'incident_views_user_incident_idx')) {
            Schema::table('incident_views', function (Blueprint $table) {
                $table->index(['user_id', 'incident_id'], 'incident_views_user_incident_idx');
            });
        }
    }

    public function down(): void
    {
        if ($this->indexExists('incidents', 'incidents_type_idx')) {
            Schema::table('incidents', function (Blueprint $table) {
                $table->dropIndex('incidents_type_idx');
            });
        }

        if ($this->indexExists('incidents', 'incidents_status_idx')) {
            Schema::table('incidents', function (Blueprint $table) {
                $table->dropIndex('incidents_status_idx');
            });
        }

        if ($this->indexExists('incidents', 'incidents_severity_idx')) {
            Schema::table('incidents', function (Blueprint $table) {
                $table->dropIndex('incidents_severity_idx');
            });
        }

        if ($this->indexExists('incidents', 'incidents_created_at_idx')) {
            Schema::table('incidents', function (Blueprint $table) {
                $table->dropIndex('incidents_created_at_idx');
            });
        }

        if ($this->indexExists('incident_views', 'incident_views_user_incident_idx')) {
            Schema::table('incident_views', function (Blueprint $table) {
                $table->dropIndex('incident_views_user_incident_idx');
            });
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        try {
            $result = DB::select(
                "SELECT 1 FROM pg_indexes WHERE tablename = ? AND indexname = ?",
                [$table, $index]
            );
            return count($result) > 0;
        } catch (\Exception $e) {
            return false;
        }
    }
};