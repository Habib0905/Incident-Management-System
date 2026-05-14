<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            $table->string('embedding')->nullable();
            $table->timestamp('last_embedded_at')->nullable();
        });

        DB::statement('ALTER TABLE incidents ALTER COLUMN embedding TYPE vector(384) USING embedding::vector(384)');
    }

    public function down(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            $table->dropColumn(['embedding', 'last_embedded_at']);
        });
    }
};
