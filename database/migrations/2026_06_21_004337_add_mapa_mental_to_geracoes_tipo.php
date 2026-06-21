<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE geracoes DROP CONSTRAINT IF EXISTS geracoes_tipo_check');
        DB::statement("ALTER TABLE geracoes ADD CONSTRAINT geracoes_tipo_check CHECK (tipo IN ('resumo','flashcards','simulado','mapa_mental'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE geracoes DROP CONSTRAINT IF EXISTS geracoes_tipo_check');
        DB::statement("ALTER TABLE geracoes ADD CONSTRAINT geracoes_tipo_check CHECK (tipo IN ('resumo','flashcards','simulado'))");
    }
};
