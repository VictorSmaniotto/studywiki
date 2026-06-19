<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('resposta_simulados', function (Blueprint $table) {
            $table->unsignedInteger('tempo_realizado_segundos')->nullable()->after('notas_dissertativas');
        });
    }

    public function down(): void
    {
        Schema::table('resposta_simulados', function (Blueprint $table) {
            $table->dropColumn('tempo_realizado_segundos');
        });
    }
};
