<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('geracoes', function (Blueprint $table) {
            $table->unsignedSmallInteger('regeneracoes')->default(0)->after('modelo');
        });
    }

    public function down(): void
    {
        Schema::table('geracoes', function (Blueprint $table) {
            $table->dropColumn('regeneracoes');
        });
    }
};
