<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Exception;

class VideoProcessingService
{
    /**
     * Converte, compacta e corrige metadados do vídeo.
     * Retorna o caminho do arquivo processado.
     */
    public function optimizeVideo(string $inputPath, string $outputPath): string
    {
        // Garante que o diretório de saída existe
        File::ensureDirectoryExists(dirname($outputPath));

        // Comando FFmpeg Otimizado para Oitivas Policiais:
        // -c:v libx264: Codec padrão universal.
        // -preset veryfast: Compactação rápida (poupa CPU do servidor).
        // -crf 26: Qualidade visual boa, mas arquivo leve (padrão é 23, quanto maior, menor o arquivo).
        // -c:a aac: Áudio compatível com todos navegadores.
        // -movflags +faststart: CRUCIAL para web. Permite dar play sem baixar o vídeo todo.
        // -af asetpts=PTS-STARTPTS: Corrige desincronia de áudio comum em gravações WebRTC.
        
        $command = [
            'ffmpeg',
            '-y', // Sobrescreve se existir
            '-i', $inputPath,
            '-c:v', 'libx264',
            '-preset', 'veryfast',
            '-crf', '26', 
            '-c:a', 'aac',
            '-b:a', '128k',
            '-movflags', '+faststart',
            '-af', 'asetpts=PTS-STARTPTS',
            $outputPath
        ];

        Log::info("Iniciando FFmpeg: " . implode(' ', $command));

        $result = Process::timeout(1200) // 20 minutos de timeout
            ->run($command);

        if ($result->failed()) {
            Log::error("Falha no FFmpeg: " . $result->errorOutput());
            throw new Exception("Erro ao processar vídeo: " . $result->errorOutput());
        }

        return $outputPath;
    }

    /**
     * Calcula o Hash SHA-256 para integridade forense.
     */
    public function generateHash(string $filePath): string
    {
        if (!File::exists($filePath)) {
            throw new Exception("Arquivo não encontrado para hash: $filePath");
        }
        return hash_file('sha256', $filePath);
    }

    /**
     * Envia para o S3/MinIO e retorna o path relativo.
     */
    public function uploadToCloud(string $localPath, string $targetPath): string
    {
        $stream = fopen($localPath, 'r+');
        
        // putFileAs faz o streaming (não carrega tudo na RAM)
        $uploaded = Storage::disk('s3')->put(
            $targetPath, 
            $stream, 
            ['visibility' => 'private'] // Segurança policial
        );

        if (is_resource($stream)) {
            fclose($stream);
        }

        if (!$uploaded) {
            throw new Exception("Falha ao enviar para o S3.");
        }

        return $targetPath;
    }
}