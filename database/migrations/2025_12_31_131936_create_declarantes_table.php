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
        Schema::create('declarantes', function (Blueprint $table) {
            $table->id();
            $table->string('nome_completo');
            $table->string('cpf', 14)->unique()->nullable();
            $table->date('data_nascimento')->nullable();
            $table->string('nome_mae')->nullable();
            // Dados de contato podem ser úteis
            $table->string('telefone')->nullable();
            $table->string('email')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('declarantes');
    }
};
