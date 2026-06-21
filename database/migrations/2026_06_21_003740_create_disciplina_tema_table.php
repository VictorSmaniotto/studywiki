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
        Schema::create('disciplina_tema', function (Blueprint $table) {
            $table->foreignId('disciplina_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tema_id')->constrained()->cascadeOnDelete();
            $table->primary(['disciplina_id', 'tema_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('disciplina_tema');
    }
};
