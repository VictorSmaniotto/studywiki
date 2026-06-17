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
        Schema::create('geracao_fontes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('geracao_id')->constrained('geracoes')->cascadeOnDelete();
            $table->foreignId('pagina_id')->constrained()->cascadeOnDelete();
            $table->foreignId('chunk_id')->nullable()->constrained('chunks')->nullOnDelete();
            $table->unique(['geracao_id', 'pagina_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('geracao_fontes');
    }
};
