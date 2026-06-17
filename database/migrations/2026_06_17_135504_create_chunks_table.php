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
        Schema::create('chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pagina_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('ordem');
            $table->text('conteudo');
            $table->string('heading_path')->nullable();
            $table->unsignedSmallInteger('tokens')->nullable();
            $table->vector('embedding', 1536)->nullable();
            $table->string('embedding_model')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chunks');
    }
};
