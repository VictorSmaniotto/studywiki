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
        Schema::create('paginas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('disciplina_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('tipo', ['disciplina', 'conceito', 'autor', 'fonte', 'sintese']);
            $table->string('titulo');
            $table->string('slug');
            $table->string('path_relativo')->unique();
            $table->jsonb('frontmatter')->default('{}');
            $table->text('corpo')->nullable();
            $table->string('hash', 64);
            $table->timestamp('atualizado_na_vault')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('paginas');
    }
};
