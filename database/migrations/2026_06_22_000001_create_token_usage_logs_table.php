<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('token_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedInteger('cache_write_tokens')->default(0);
            $table->unsignedInteger('cache_read_tokens')->default(0);
            $table->decimal('custo_estimado_usd', 10, 6)->default(0);
            $table->string('origem'); // geracao|chat|embed
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('token_usage_logs');
    }
};
