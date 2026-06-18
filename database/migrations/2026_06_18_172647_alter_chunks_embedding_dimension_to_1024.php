<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Nullify existing embeddings (dimension mismatch would break the cast)
        DB::statement('UPDATE chunks SET embedding = NULL, embedding_model = NULL WHERE embedding IS NOT NULL');

        // Change vector dimension 1536 → 1024 (VoyageAI voyage-3-lite)
        DB::statement('ALTER TABLE chunks ALTER COLUMN embedding TYPE vector(1024)');

        // HNSW index for cosine similarity (Fase 4 retrieval)
        DB::statement('CREATE INDEX IF NOT EXISTS chunks_embedding_hnsw ON chunks USING hnsw (embedding vector_cosine_ops)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS chunks_embedding_hnsw');
        DB::statement('UPDATE chunks SET embedding = NULL, embedding_model = NULL WHERE embedding IS NOT NULL');
        DB::statement('ALTER TABLE chunks ALTER COLUMN embedding TYPE vector(1536)');
    }
};
