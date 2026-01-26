<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('oitivas', function (Blueprint $table) {
            $table->text('transcricao')->nullable()->after('hash_arquivo_video')
                ->comment('Transcrição em formato SRT gerada pelo Whisper');
            $table->boolean('processando_transcricao')->default(false)->after('transcricao')
                ->comment('Flag para indicar se a transcrição está sendo processada');
        });
    }

    public function down(): void
    {
        Schema::table('oitivas', function (Blueprint $table) {
            $table->dropColumn(['transcricao', 'processando_transcricao']);
        });
    }
};
