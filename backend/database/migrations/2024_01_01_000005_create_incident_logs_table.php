<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incident_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_id')->constrained('incidents')->cascadeOnDelete();
            $table->foreignId('log_id')->constrained('logs')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['incident_id', 'log_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_logs');
    }
};