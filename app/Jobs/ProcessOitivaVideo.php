<?php

namespace App\Jobs;

use App\Models\Oitiva;
use App\Services\VideoProcessingService;
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

    // Aumentamos o timeout do Job para garantir que o FFmpeg tenha tempo
    public $timeout = 1800; // 30 minutos

    protected $oitivaId;
    protected $rawVideoPath;

    public function __construct($oitivaId, $rawVideoPath)
    {
        $this->oitivaId = $oitivaId;
        $this->rawVideoPath = $rawVideoPath;
    }

    public function handle(VideoProcessingService $processor)
    {
        $oitiva = Oitiva::find($this->oitivaId);

        if (!$oitiva) {
            Log::error("Oitiva #{$this->oitivaId} não encontrada para processamento.");
            return;
        }

        // 1. Atualiza Status para Processando
        // (Sugiro criar uma coluna 'status_processamento' ou usar enum no banco)
        $oitiva->update(['observacoes' => $oitiva->observacoes . "\n[Sistema]: Processando vídeo..."]);

        $processedPath = null;

        try {
            Log::info("Iniciando Job de Oitiva #{$this->oitivaId}");

            // Define onde será salvo o arquivo convertido localmente antes do upload
            $tempDir = storage_path('app/temp_processed');
            $processedFilename = $oitiva->uuid . '.mp4'; // Padroniza para MP4
            $processedPath = $tempDir . '/' . $processedFilename;

            // 2. Processamento (FFmpeg)
            $processor->optimizeVideo($this->rawVideoPath, $processedPath);

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
            
            $processor->uploadToCloud($processedPath, $s3Path);

            // 5. Atualiza Banco de Dados
            $oitiva->update([
                'caminho_arquivo_video' => $s3Path,
                'hash_arquivo_video' => $hash,
                'data_fim_gravacao' => now(), // Ou mantém o original se já tiver
                'observacoes' => $oitiva->observacoes . "\n[Sistema]: Vídeo processado com sucesso.",
            ]);

            Log::info("Vídeo da Oitiva #{$this->oitivaId} finalizado com sucesso.");

            // Disparar outros Jobs aqui se necessário (Transcrições, etc)

        } catch (\Exception $e) {
            Log::error("Erro no Job Oitiva #{$this->oitivaId}: " . $e->getMessage());
            $oitiva->update(['observacoes' => $oitiva->observacoes . "\n[Erro]: Falha no processamento de vídeo."]);
            
            // Opcional: Fail the job to retry later
            $this->fail($e);

        } finally {
            // 6. Limpeza (Cleanup)
            // Apaga o arquivo RAW (WebM) e o Processado (MP4) locais
            if (File::exists($this->rawVideoPath)) {
                File::delete($this->rawVideoPath);
            }
            if ($processedPath && File::exists($processedPath)) {
                File::delete($processedPath);
            }
        }
    }
}