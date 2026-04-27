<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            $table->dropForeign(['server_id']);
            $table->foreign('server_id')
                ->references('id')
                ->on('servers')
                ->cascadeOnDelete();
        });

        Schema::table('incident_views', function (Blueprint $table) {
            $table->dropForeign(['incident_id']);
            $table->foreign('incident_id')
                ->references('id')
                ->on('incidents')
                ->cascadeOnDelete();
        });

        Schema::table('activity_timeline', function (Blueprint $table) {
            $table->dropForeign(['incident_id']);
            $table->foreign('incident_id')
                ->references('id')
                ->on('incidents')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            $table->dropForeign(['server_id']);
            $table->foreign('server_id')
                ->references('id')
                ->on('servers')
                ->nullOnDelete();
        });

        Schema::table('incident_views', function (Blueprint $table) {
            $table->dropForeign(['incident_id']);
            $table->foreign('incident_id')
                ->references('id')
                ->on('incidents');
        });

        Schema::table('activity_timeline', function (Blueprint $table) {
            $table->dropForeign(['incident_id']);
            $table->foreign('incident_id')
                ->references('id')
                ->on('incidents');
        });
    }
};
