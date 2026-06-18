<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            "CREATE INDEX IF NOT EXISTS chunks_fts_gin ON chunks USING gin(to_tsvector('portuguese', conteudo))"
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS chunks_fts_gin');
    }
};
