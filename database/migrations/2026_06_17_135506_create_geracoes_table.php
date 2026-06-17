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
        Schema::create('geracoes', function (Blueprint $table) {
            $table->id();
            $table->enum('tipo', ['resumo', 'flashcards', 'simulado']);
            $table->jsonb('escopo')->default('{}');
            $table->enum('status', ['ok', 'rejeitado']);
            $table->jsonb('payload')->default('{}');
            $table->unsignedInteger('custo_tokens')->default(0);
            $table->string('modelo');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('geracoes');
    }
};
