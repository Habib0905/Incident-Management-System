<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained('servers')->cascadeOnDelete();
            $table->text('message');
            $table->string('log_level')->nullable();
            $table->string('source')->nullable();
            $table->timestamp('timestamp');
            $table->json('raw_payload')->nullable();
            $table->timestamps();
            $table->index('server_id');
            $table->index('log_level');
            $table->index('timestamp');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logs');
    }
};