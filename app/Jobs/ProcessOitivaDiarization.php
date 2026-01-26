<?php

namespace App\Jobs;

use App\Models\Oitiva;
use GuzzleHttp\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessOitivaDiarization implements ShouldQueue
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
        $startTime = microtime(true);

        $oitiva = Oitiva::find($this->oitivaId);

        if (!$oitiva) {
            Log::error("Oitiva #{$this->oitivaId} não encontrada para transcrição.");
            return;
        }

        try {
            Log::info("Iniciando processamento de transcrição para Oitiva #{$this->oitivaId}");

            // Marca como processando
            $oitiva->update(['processando_transcricao' => true]);

            // Baixa o vídeo do MinIO (S3)
            $videoPath = $this->downloadFromMinIO($oitiva->caminho_arquivo_video, $oitiva->uuid);

            // Extrai o áudio do vídeo
            $audioPath = $this->extractAudioFile($videoPath, $oitiva->uuid);

            // Envia para a API Whisper e obtém transcrição
            $transcricaoRaw = $this->sendToWhisperAPI($audioPath);

            // Normaliza e valida o SRT usando o helper
            $transcricao = \App\Helpers\SrtHelper::normalize($transcricaoRaw);
            $validation = \App\Helpers\SrtHelper::validate($transcricao);

            if (!$validation['valid']) {
                Log::error("SRT inválido retornado pela API", [
                    'oitiva_id' => $this->oitivaId,
                    'errors' => $validation['errors']
                ]);
                throw new \Exception('Transcrição retornada é inválida: ' . implode(', ', $validation['errors']));
            }

            if (!empty($validation['warnings'])) {
                Log::warning("Avisos na validação do SRT", [
                    'oitiva_id' => $this->oitivaId,
                    'warnings' => $validation['warnings']
                ]);
            }

            Log::info("SRT validado com sucesso", [
                'oitiva_id' => $this->oitivaId,
                'segments' => $validation['segment_count']
            ]);

            // Salva a transcrição no banco
            $oitiva->update([
                'transcricao' => $transcricao,
                'processando_transcricao' => false
            ]);

            // Limpa arquivos temporários
            if (File::exists($videoPath)) {
                File::delete($videoPath);
            }
            if (File::exists($audioPath)) {
                File::delete($audioPath);
            }

            // Calcula tempo de execução
            $endTime = microtime(true);
            $executionTime = $endTime - $startTime;
            $minutes = floor($executionTime / 60);
            $seconds = $executionTime % 60;

            Log::info("Transcrição concluída para Oitiva #{$this->oitivaId} em {$minutes} minutos e {$seconds} segundos.");

        } catch (\Throwable $th) {
            Log::error("Erro no processamento de transcrição da Oitiva #{$this->oitivaId}");
            Log::error($th->getMessage());
            Log::error($th->getTraceAsString());

            $oitiva->update(['processando_transcricao' => false]);
        }
    }

    /**
     * Baixa o vídeo do MinIO (S3) para um arquivo temporário local
     */
    private function downloadFromMinIO($cloudPath, $uuid)
    {
        $tempDir = storage_path('app/temp_transcription');
        if (!File::isDirectory($tempDir)) {
            File::makeDirectory($tempDir, 0755, true);
        }

        $tempPath = $tempDir . '/' . $uuid . '.mp4';

        Log::info("Baixando vídeo do MinIO: {$cloudPath} para {$tempPath}");

        // Baixa do S3/MinIO
        $fileContent = Storage::disk('s3')->get($cloudPath);
        File::put($tempPath, $fileContent);

        Log::info("Download do MinIO concluído: " . filesize($tempPath) . " bytes");

        return $tempPath;
    }

    /**
     * Extrai o áudio do vídeo usando FFmpeg
     */
    private function extractAudioFile($videoPath, $uuid)
    {
        $audioDir = storage_path('app/temp_transcription/audio');
        if (!File::isDirectory($audioDir)) {
            File::makeDirectory($audioDir, 0755, true);
        }

        $audioPath = $audioDir . '/' . $uuid . '.wav';

        Log::info("Extraindo áudio do vídeo...");

        // - 16kHz sample rate (padrão do Whisper)
        // - mono (reduz tamanho sem perder qualidade)
        // - Normalização de áudio para melhor qualidade
        $ffmpegCommand = sprintf(
            'ffmpeg -i %s -ar 16000 -ac 1 -c:a pcm_s16le -af "loudnorm=I=-16:TP=-1.5:LRA=11" %s -y 2>&1',
            escapeshellarg($videoPath),
            escapeshellarg($audioPath)
        );

        exec($ffmpegCommand, $output, $returnVar);

        if ($returnVar !== 0) {
            Log::error("Erro no FFmpeg", ['output' => implode("\n", $output)]);
            throw new \Exception("Erro ao converter vídeo em áudio: " . implode("\n", $output));
        }

        if (!File::exists($audioPath) || filesize($audioPath) === 0) {
            throw new \Exception("Arquivo de áudio não foi gerado corretamente");
        }

        Log::info("Áudio extraído com sucesso", [
            'size' => filesize($audioPath),
            'format' => '16kHz mono PCM'
        ]);

        return $audioPath;
    }

    /**
     * Envia o áudio para a API Whisper e retorna a transcrição
     */
    private function sendToWhisperAPI($audioPath)
    {
        Log::info("Enviando áudio para API Whisper...");

        $client = new Client([
            'timeout' => 300, // 5 minutos
            'connect_timeout' => 30
        ]);

        $apiWhisperUrl = env('WHISPER_BASE_URL') . '/asr?language=pt&output=srt';

        Log::debug("URL da API Whisper: " . $apiWhisperUrl);
        Log::debug("Tamanho do arquivo de áudio: " . filesize($audioPath) . " bytes");

        try {
            $startTime = microtime(true);

            $response = $client->request('POST', $apiWhisperUrl, [
                'multipart' => [
                    [
                        'name'     => 'audio_file',
                        'contents' => file_get_contents($audioPath),
                        'filename' => 'audio.wav'
                    ]
                ],
            ]);

            $duration = round(microtime(true) - $startTime, 2);
            Log::info("API Whisper respondeu em {$duration}s");

            if ($response->getStatusCode() !== 200) {
                throw new \Exception('Erro na resposta da API Whisper: ' . $response->getStatusCode());
            }

            $transcricao = (string) $response->getBody();

            // Validação do SRT
            if (empty($transcricao) || !str_contains($transcricao, '-->')) {
                Log::error('Transcrição inválida recebida', [
                    'size' => strlen($transcricao),
                    'preview' => substr($transcricao, 0, 500)
                ]);
                throw new \Exception('Transcrição retornada pela API Whisper parece inválida');
            }

            Log::info('Transcrição recebida com sucesso', [
                'size' => strlen($transcricao),
                'segments' => substr_count($transcricao, '-->'),
                'preview' => substr($transcricao, 0, 200) . '...'
            ]);

            return $transcricao;

        } catch (\GuzzleHttp\Exception\RequestException $e) {
            Log::error('Erro na requisição para API Whisper', [
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);

            if ($e->hasResponse()) {
                $responseBody = (string) $e->getResponse()->getBody();
                Log::error('Resposta de erro da API', ['body' => $responseBody]);
            }

            throw new \Exception('Falha ao comunicar com API Whisper: ' . $e->getMessage());
        }
    }
}
