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
        Schema::create('oitivas', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique(); // Para links seguros ou QR Codes
            
            // Relacionamentos
            $table->foreignId('user_id')->constrained()->comment('Policial que realizou a oitiva');
            $table->foreignId('unidade_id')->nullable()->constrained();
            $table->foreignId('declarante_id')->constrained();
            $table->foreignId('representante_id')->nullable()->constrained();
            
            // Dados do Procedimento
            $table->string('numero_inquerito');
            $table->string('nome_delegado_responsavel'); // Pode ser string ou FK para User se todos delegados tiverem cadastro
            $table->string('tipo_oitiva'); // Cast para Enum TipoOitiva
            
            // Dados do Vídeo e Segurança
            $table->string('caminho_arquivo_video'); // Path no Storage (MinIO/S3/Local)
            $table->string('hash_arquivo_video'); // SHA-256 do arquivo físico para garantir integridade
            $table->text('assinatura_biometrica')->nullable(); // Hash ou JSON da biometria
            
            // Metadados
            $table->text('observacoes')->nullable();
            $table->timestamp('data_inicio_gravacao')->nullable();
            $table->timestamp('data_fim_gravacao')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('oitivas');
    }
};
