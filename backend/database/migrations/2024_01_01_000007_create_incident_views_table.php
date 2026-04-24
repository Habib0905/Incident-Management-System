<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incident_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_id')->constrained('incidents');
            $table->foreignId('user_id')->constrained('users');
            $table->timestamp('viewed_at')->useCurrent();
            $table->unique(['incident_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_views');
    }
};