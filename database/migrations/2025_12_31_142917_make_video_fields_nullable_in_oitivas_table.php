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
        Schema::table('oitivas', function (Blueprint $table) {
            $table->string('caminho_arquivo_video')->nullable()->change();
            $table->string('hash_arquivo_video')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('oitivas', function (Blueprint $table) {
            $table->string('caminho_arquivo_video')->nullable(false)->change();
            $table->string('hash_arquivo_video')->nullable(false)->change();
        });
    }
};
