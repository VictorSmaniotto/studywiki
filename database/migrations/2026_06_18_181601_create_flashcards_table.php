<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flashcards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('geracao_id')->constrained('geracoes')->cascadeOnDelete();
            $table->text('frente');
            $table->text('verso');
            $table->json('fontes')->default('[]');
            $table->date('proxima_revisao')->default(now()->toDateString());
            $table->unsignedSmallInteger('intervalo')->default(1);
            $table->decimal('facilidade', 4, 2)->default(2.50);
            $table->unsignedSmallInteger('repeticoes')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flashcards');
    }
};
