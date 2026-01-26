<?php

namespace App\Jobs;

use App\Models\Oitiva;
use App\Services\VideoProcessingService;
use App\Jobs\ProcessOitivaDiarization;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProcessOitivaVideo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 1800; // 30 minutos
    public $tries = 2; // Tenta 2 vezes em caso de falha

    protected $oitivaId;
    protected $rawVideoPath;
    protected $sessionDir;

    public function __construct($oitivaId, $rawVideoPath, $sessionDir = null)
    {
        $this->oitivaId = $oitivaId;
        $this->rawVideoPath = $rawVideoPath;
        $this->sessionDir = $sessionDir;
    }

    public function handle(VideoProcessingService $processor)
    {
        $startTime = microtime(true);

        $oitiva = Oitiva::find($this->oitivaId);

        if (!$oitiva) {
            Log::error("Oitiva #{$this->oitivaId} não encontrada para processamento.");
            $this->cleanupSession();
            return;
        }

        // 1. Atualiza Status para Processando (usa status_processamento se existir, senão observacoes)
        $this->updateStatus($oitiva, 'processing', 'Processando vídeo...');

        $processedPath = null;

        try {
            Log::info("Iniciando processamento de vídeo", [
                'oitiva_id' => $this->oitivaId,
                'raw_file' => $this->rawVideoPath,
                'raw_size' => File::exists($this->rawVideoPath) ? File::size($this->rawVideoPath) : 0
            ]);

            // Valida arquivo de entrada
            if (!File::exists($this->rawVideoPath)) {
                throw new \Exception("Arquivo raw não encontrado: {$this->rawVideoPath}");
            }

            $rawSize = File::size($this->rawVideoPath);
            if ($rawSize === 0) {
                throw new \Exception("Arquivo raw está vazio");
            }

            // Define onde será salvo o arquivo convertido
            $tempDir = storage_path('app/temp_processed');
            if (!File::isDirectory($tempDir)) {
                File::makeDirectory($tempDir, 0755, true);
            }

            $processedFilename = $oitiva->uuid . '.mp4'; // Padroniza para MP4
            $processedPath = $tempDir . '/' . $processedFilename;

            // 2. Processamento (FFmpeg)
            Log::info("Iniciando otimização FFmpeg", [
                'oitiva_id' => $this->oitivaId,
                'input' => $this->rawVideoPath,
                'output' => $processedPath
            ]);

            $processor->optimizeVideo($this->rawVideoPath, $processedPath);

            // Valida arquivo processado
            if (!File::exists($processedPath)) {
                throw new \Exception("Arquivo processado não foi gerado pelo FFmpeg");
            }

            $processedSize = File::size($processedPath);
            if ($processedSize === 0) {
                throw new \Exception("Arquivo processado está vazio");
            }

            Log::info("FFmpeg concluído", [
                'oitiva_id' => $this->oitivaId,
                'raw_size' => $rawSize,
                'processed_size' => $processedSize,
                'compression_ratio' => round(($rawSize - $processedSize) / $rawSize * 100, 2) . '%'
            ]);

           // 3. Integridade (Hash do arquivo FINAL processado)
           // Importante: O hash deve ser do arquivo que vai para o S3, não do raw.
            $hash = $processor->generateHash($processedPath);

            // 4. Upload para S3
            $s3Path = sprintf(
                'oitivas/%s/%s/%s.mp4',
                now()->format('Y'),
                now()->format('m'),
                $oitiva->uuid
            );

            Log::info("Iniciando upload para S3", [
                'oitiva_id' => $this->oitivaId,
                's3_path' => $s3Path
            ]);

            $processor->uploadToCloud($processedPath, $s3Path);

            // 5. Atualiza Banco de Dados
            $oitiva->update([
                'caminho_arquivo_video' => $s3Path,
                'hash_arquivo_video' => $hash,
                'tamanho_arquivo_video' => $processedSize,
                'data_fim_gravacao' => now(), // Ou mantém o original se já tiver
            ]);

            $this->updateStatus($oitiva, 'completed', 'Vídeo processado com sucesso.');

            $duration = round(microtime(true) - $startTime, 2);

            Log::info("Vídeo processado com sucesso", [
                'oitiva_id' => $this->oitivaId,
                's3_path' => $s3Path,
                'hash' => $hash,
                'size' => $processedSize,
                'duration' => $duration . 's'
            ]);

            // 6. Dispara Job de Transcrição
            ProcessOitivaDiarization::dispatch($this->oitivaId);
            Log::info("Job de transcrição disparado", ['oitiva_id' => $this->oitivaId]);

        } catch (\Exception $e) {
            Log::error("Erro no processamento de vídeo", [
                'oitiva_id' => $this->oitivaId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->updateStatus($oitiva, 'failed', 'Falha no processamento de vídeo: ' . $e->getMessage());

            // Re-throw para que o Laravel tente novamente se tries > 1
            throw $e;

        } finally {
            // 7. Limpeza (Cleanup)
            // Apaga o arquivo RAW (WebM) e o Processado (MP4) locais
            $this->cleanup($processedPath);
        }
    }

    /**
     * Atualiza status da oitiva
     */
    private function updateStatus($oitiva, string $status, string $message): void
    {
        $updateData = [];

        // Se a coluna status_processamento existir, usa ela
        if (in_array('status_processamento', array_keys($oitiva->getAttributes()))) {
            $updateData['status_processamento'] = $status;
        }

        $timestamp = now()->format('Y-m-d H:i:s');
        $updateData['observacoes'] = ($oitiva->observacoes ?? '') . "\n[{$timestamp}]: {$message}";

        $oitiva->update($updateData);
    }

    /**
     * Limpeza de arquivos temporários
     */
    private function cleanup(?string $processedPath): void
    {
        try {
            // Limpa arquivo raw
            if (File::exists($this->rawVideoPath)) {
                File::delete($this->rawVideoPath);
                Log::info("Arquivo raw deletado", [
                    'oitiva_id' => $this->oitivaId,
                    'path' => $this->rawVideoPath
                ]);
            }

            // Limpa arquivo processado
            if ($processedPath && File::exists($processedPath)) {
                File::delete($processedPath);
                Log::info("Arquivo processado deletado", [
                    'oitiva_id' => $this->oitivaId,
                    'path' => $processedPath
                ]);
            }

            // Limpa sessão de upload se fornecida
            $this->cleanupSession();

        } catch (\Exception $e) {
            Log::error("Erro na limpeza de arquivos", [
                'oitiva_id' => $this->oitivaId,
                'error' => $e->getMessage()
            ]);
            // Não re-throw - falha na limpeza não deve falhar o job
        }
    }

    /**
     * Limpa sessão de upload (chunks)
     */
    private function cleanupSession(): void
    {
        if (!$this->sessionDir) {
            return;
        }

        try {
            if (File::isDirectory($this->sessionDir)) {
                File::deleteDirectory($this->sessionDir);

                Log::info("Sessão de upload limpa", [
                    'oitiva_id' => $this->oitivaId,
                    'session_dir' => $this->sessionDir
                ]);
            }
        } catch (\Exception $e) {
            Log::error("Erro ao limpar sessão de upload", [
                'oitiva_id' => $this->oitivaId,
                'session_dir' => $this->sessionDir,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
      (após todas as tentativas)
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("Job falhou após todas tentativas", [
            'oitiva_id' => $this->oitivaId,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts()
        ]);

        // Atualiza status final
        $oitiva = Oitiva::find($this->oitivaId);
        if ($oitiva) {
            $this->updateStatus($oitiva, 'failed', 'Processamento falhou após múltiplas tentativas.');
        }

        // Última tentativa de limpeza
        $this->cleanupSession();
    }
}
