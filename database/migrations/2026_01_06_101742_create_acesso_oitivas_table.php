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
        Schema::create('acesso_oitivas', function (Blueprint $table) {
            $table->id();
            // Relacionamento com a oitiva
            $table->foreignId('oitiva_id')->constrained()->cascadeOnDelete();
            
            // Dados do Servidor que assistiu
            $table->string('nome_servidor');
            $table->string('matricula_servidor');
            
            // Metadados do acesso
            $table->string('tipo_acesso')->default('visualizacao');
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent')->nullable(); // Para saber navegador/OS
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('acesso_oitivas');
    }
};
