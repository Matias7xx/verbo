<?php

namespace App\Jobs;

use App\Models\Oitiva;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class ProcessVideoDownloadZip implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 1800; // 30 minutos

    protected $oitivaId;

    public function __construct($oitivaId)
    {
        $this->oitivaId = $oitivaId;
    }

    public function handle()
    {
        $oitiva = Oitiva::find($this->oitivaId);

        if (!$oitiva) {
            Log::error("Oitiva #{$this->oitivaId} não encontrada para download.");
            return;
        }

        try {
            Log::info("Iniciando processamento de download ZIP para Oitiva #{$this->oitivaId}");

            // Marca como processando
            $oitiva->update(['status_download' => 'processing']);

            // BAIXA O VÍDEO DO MINIO (S3)
            $videoPath = $this->downloadFromMinIO($oitiva->caminho_arquivo_video, $oitiva->uuid);

            // Processa e cria o ZIP
            $zipPath = $this->processAndZipVideo($videoPath, $oitiva);

            // Atualiza o registro com o caminho do ZIP
            $oitiva->update([
                'status_download' => 'completed',
                'download_zip_path' => $zipPath
            ]);

            // Limpa arquivo temporário do vídeo baixado
            if (File::exists($videoPath)) {
                File::delete($videoPath);
            }

            Log::info("ZIP criado com sucesso para Oitiva #{$this->oitivaId}: {$zipPath}");

        } catch (\Throwable $th) {
            Log::error("Erro no processamento de download ZIP da Oitiva #{$this->oitivaId}");
            Log::error($th->getMessage());
            Log::error($th->getTraceAsString());

            $oitiva->update(['status_download' => 'failed']);
        }
    }

    /**
     * Baixa o vídeo do MinIO (S3) para um arquivo temporário local
     */
    private function downloadFromMinIO($cloudPath, $uuid)
    {
        $tempDir = storage_path('app/temp_download');
        if (!File::isDirectory($tempDir)) {
            File::makeDirectory($tempDir, 0755, true);
        }

        $tempPath = $tempDir . '/' . $uuid . '.mp4';

        Log::info("Baixando vídeo do MinIO: {$cloudPath} para {$tempPath}");

        // Baixa do S3/MinIO usando o disco configurado
        $fileContent = Storage::disk('s3')->get($cloudPath);
        File::put($tempPath, $fileContent);

        Log::info("Download do MinIO concluído: " . filesize($tempPath) . " bytes");

        return $tempPath;
    }

    /**
     * Processa o vídeo (divide em partes) e cria o ZIP
     */
    private function processAndZipVideo($videoPath, $oitiva)
    {
        $filename = $oitiva->uuid;
        $outputDir = storage_path('app/temp_chunks/' . $filename);

        // Cria diretório de saída
        if (!File::isDirectory($outputDir)) {
            File::makeDirectory($outputDir, 0755, true);
        }

        Log::info("Dividindo vídeo em partes de 15MB...");

        // Divide o vídeo em partes de no máximo 15MB usando FFmpeg
        $segmentTime = 60; // segundos por segmento

        $ffmpegCommand = sprintf(
            'ffmpeg -i %s -f segment -segment_time %d -reset_timestamps 1 -fs 15M -c:v libx264 -preset fast -crf 23 -c:a aac -b:a 192k -filter:v fps=24 %s 2>&1',
            escapeshellarg($videoPath),
            $segmentTime,
            escapeshellarg($outputDir . '/' . $filename . '_%03d.mp4')
        );

        exec($ffmpegCommand, $output, $returnVar);

        if ($returnVar !== 0) {
            Log::error("Erro ao dividir o vídeo com FFmpeg");
            Log::error("Comando: " . $ffmpegCommand);
            Log::error("Output: " . implode("\n", $output));
            throw new \Exception("Erro ao dividir o vídeo: " . implode("\n", $output));
        }

        Log::info("Vídeo dividido com sucesso");

        // Coleta os arquivos gerados
        $videoFiles = [];
        foreach (range(0, 999) as $number) {
            $formattedNumber = sprintf('%03d', $number);
            $file = $outputDir . '/' . $filename . '_' . $formattedNumber . '.mp4';
            if (File::exists($file)) {
                $videoFiles[] = $file;
            } else {
                break;
            }
        }

        Log::info("Total de partes geradas: " . count($videoFiles));

        if (empty($videoFiles)) {
            throw new \Exception("Nenhum arquivo de vídeo foi gerado pela divisão");
        }

        // Cria o ZIP
        $zipDir = storage_path('app/downloads');
        if (!File::isDirectory($zipDir)) {
            File::makeDirectory($zipDir, 0755, true);
        }

        $zipPath = $zipDir . '/' . $filename . '.zip';
        $zip = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \Exception("Erro ao criar o arquivo ZIP.");
        }

        Log::info("Criando arquivo ZIP com hashes...");

        // Arquivo de hashes
        $hashesContent = "SISTEMA VERBO - Arquivo de Verificação de Integridade\n";
        $hashesContent .= str_repeat("=", 80) . "\n";
        $hashesContent .= "Oitiva UUID: {$oitiva->uuid}\n";
        $hashesContent .= "Inquérito: {$oitiva->numero_inquerito}\n";
        $hashesContent .= "Data de Geração: " . now()->format('d/m/Y H:i:s') . "\n";
        $hashesContent .= "Total de Partes: " . count($videoFiles) . "\n";
        $hashesContent .= str_repeat("=", 80) . "\n\n";

        foreach ($videoFiles as $file) {
            $basename = basename($file);
            $hash = hash_file('sha256', $file);

            $hashesContent .= "Arquivo: {$basename}\n";
            $hashesContent .= "Hash SHA-256: {$hash}\n";
            $hashesContent .= "Tamanho: " . $this->formatBytes(filesize($file)) . "\n\n";

            $zip->addFile($file, $basename);
        }

        // Adiciona arquivo de hashes ao ZIP
        $zip->addFromString($filename . '_hashes.txt', $hashesContent);
        $zip->close();

        Log::info("ZIP criado com sucesso: " . filesize($zipPath) . " bytes");

        // Limpa arquivos temporários
        foreach ($videoFiles as $file) {
            File::delete($file);
        }
        File::deleteDirectory($outputDir);

        // Retorna o caminho relativo para storage
        return 'downloads/' . $filename . '.zip';
    }

    /**
     * Formata bytes em formato legível
     */
    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
