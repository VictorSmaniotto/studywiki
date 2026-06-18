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
        Schema::create('resposta_simulados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('geracao_id')->constrained('geracoes')->cascadeOnDelete();
            $table->json('respostas');
            $table->unsignedSmallInteger('acertos');
            $table->unsignedSmallInteger('total');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('resposta_simulados');
    }
};
