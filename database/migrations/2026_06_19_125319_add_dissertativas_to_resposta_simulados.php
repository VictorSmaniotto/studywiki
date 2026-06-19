<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resposta_simulados', function (Blueprint $table) {
            $table->json('respostas_dissertativas')->nullable()->after('respostas');
            $table->json('notas_dissertativas')->nullable()->after('respostas_dissertativas');
        });
    }

    public function down(): void
    {
        Schema::table('resposta_simulados', function (Blueprint $table) {
            $table->dropColumn(['respostas_dissertativas', 'notas_dissertativas']);
        });
    }
};
