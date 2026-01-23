<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('oitivas', function (Blueprint $table) {
            $table->string('status_download')->nullable()->after('hash_arquivo_video')
                ->comment('pending, processing, completed, failed');
            $table->string('download_zip_path')->nullable()->after('status_download')
                ->comment('Caminho do arquivo ZIP gerado para download');
        });
    }

    public function down(): void
    {
        Schema::table('oitivas', function (Blueprint $table) {
            $table->dropColumn(['status_download', 'download_zip_path']);
        });
    }
};
