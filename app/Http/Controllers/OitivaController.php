<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOitivaRequest;
use App\Http\Requests\UpdateOitivaRequest;
use App\Jobs\ProcessOitivaVideo;
use Illuminate\Http\JsonResponse;
use App\Services\OitivaService;
use App\Models\Oitiva;
use App\Models\Declarante;
use App\Models\Representante;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Illuminate\Support\Str;

class OitivaController extends Controller
{
    // Configurações de upload
    private const CHUNK_SIZE_LIMIT = 50 * 1024 * 1024; // 50MB por chunk
    private const MAX_VIDEO_SIZE = 2 * 1024 * 1024 * 1024; // 2GB total
    private const UPLOAD_TIMEOUT = 300; // 5 minutos

    public function __construct(
        protected OitivaService $oitivaService
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $oitivas = Oitiva::with(['declarante', 'unidade'])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(10);

        return Inertia::render('Oitivas/Index', [
            'oitivas' => $oitivas
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $delegados = User::where('cargo', 'Delegado')
                        ->select('id', 'name', 'matricula')
                        ->orderBy('name')
                        ->get();

        return Inertia::render('Oitivas/Create', [
            'delegados' => $delegados
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreOitivaRequest $request): RedirectResponse
    {
        $validated = $request->validate([
            'numero_inquerito' => 'required|string',
            'delegado_id' => 'nullable|exists:users,id',
            'declarante_nome' => 'required|string',
            'declarante_cpf' => 'nullable|string', // Validar formato se desejar
            'tipo_oitiva' => 'required|string', // ex: declaracao, interrogatorio
            // ... validações do representante
        ]);

        $oitiva = DB::transaction(function () use ($request, $validated) {
            // 1. Cria ou recupera Declarante
            $declarante = Declarante::firstOrCreate(
                ['cpf' => $request->declarante_cpf],
                ['nome_completo' => $request->declarante_nome]
            );

            // 2. Cria Representante se houver
            $representante = null;
            if ($request->representante_nome) {
                $representante = Representante::create([
                    'nome_completo' => $request->representante_nome,
                    'cpf' => $request->representante_cpf,
                    'tipo' => $request->vinculo // Ajustar cast no model
                ]);
            }

            // 3. Cria a Oitiva (Ainda sem vídeo)
            return Oitiva::create([
                'uuid' => (string) Str::uuid(),
                'user_id' => $request->user()->id,
                'unidade_id' => $request->user()->unidade_id,
                'declarante_id' => $declarante->id,
                'representante_id' => $representante?->id,
                'numero_inquerito' => $validated['numero_inquerito'],
                'nome_delegado_responsavel' => $request->delegado_id ? User::find($request->delegado_id)->name : $request->user()->name,
                'tipo_oitiva' => $request->tipo_oitiva, // Certifique-se de passar o Enum ou string válida
                // Campos de vídeo ficam NULL por enquanto
            ]);
        });

        // Redireciona para a "Sala de Oitiva"
        return redirect()->route('oitivas.show', $oitiva->id);
    }

    /**
     * Display the specified resource.
     */
    public function show(Oitiva $oitiva)
    {
        // Carrega relacionamentos para exibir na tela
        $oitiva->load(['declarante', 'representante', 'user']);

        // Variável para guardar a URL segura
        $urlVideo = null;

        if ($oitiva->caminho_arquivo_video) {
            // Gera uma URL válida por 60 minutos
            // O Storage::disk('s3') usa as credenciais para assinar o link
            $urlVideo = \App\Helpers\StorageHelper::getPublicUrl(
                $oitiva->caminho_arquivo_video,
                60
            );
        }

        return Inertia::render('Oitivas/Show', [
            'oitiva' => $oitiva,
            'url_video' => $urlVideo // Passamos a URL pronta para o front
        ]);
    }

    public function uploadVideoChunk(Request $request, $id): JsonResponse
    {
        // Aumenta timeout para uploads grandes
        set_time_limit(self::UPLOAD_TIMEOUT);
        ini_set('memory_limit', '256M');

        try {
            // 1. Alteração na Validação: 'nullable' em vez de 'required'
            $request->validate([
                // 'video_part' => ['nullable', 'file', 'mimetypes:video/webm,video/x-matroska,application/octet-stream'],
                'part_number' => 'required|integer|min:1',
                'is_recording_complete' => 'required|boolean',
            ]);

            $oitiva = Oitiva::findOrFail($id);
            $partNumber = (int) $request->input('part_number');
            $isComplete = $request->boolean('is_recording_complete');

            // Caminho temporário (igual ao anterior)
            $tempDir = storage_path('app/temp_chunks');
            $sessionDir = $tempDir . '/' . $oitiva->uuid;
            $tempFileName = $oitiva->uuid . '.webm';
            $tempFilePath = $sessionDir . '/' . $tempFileName;
            $lockFile = $sessionDir . '/.lock';
            $metadataFile = $sessionDir . '/metadata.json';

            // Cria diretório de sessão se não existir
            if (!File::isDirectory($sessionDir)) {
                File::makeDirectory($sessionDir, 0755, true);
            }

            // Inicializa metadata na primeira parte
            if ($partNumber === 1 && !File::exists($metadataFile)) {
                $this->initializeMetadata($metadataFile, $oitiva->id);
            }

            // Processa o chunk se houver arquivo
            if ($request->hasFile('video_part')) {
                $file = $request->file('video_part');

                // Validações
                $this->validateChunk($file, $tempFilePath);

                // Registra parte no metadata
                $this->registerPart($metadataFile, $partNumber, $file->getSize());

                // Append chunk usando streaming (não carrega na memória)
                $this->appendChunkStreaming($file, $tempFilePath, $lockFile);

                Log::info("Chunk #{$partNumber} processado com sucesso", [
                    'oitiva_id' => $id,
                    'size' => $file->getSize(),
                    'size_mb' => round($file->getSize() / 1024 / 1024, 2),
                    'total_size' => File::size($tempFilePath),
                    'total_size_mb' => round(File::size($tempFilePath) / 1024 / 1024, 2),
                    'is_final' => $isComplete
                ]);
            } else {
                Log::info("Requisição sem arquivo (sinalização)", [
                    'oitiva_id' => $id,
                    'part_number' => $partNumber,
                    'is_complete' => $isComplete
                ]);
            }

            // 3. Se NÃO acabou, retorna sucesso (mesmo se não veio arquivo neste chunk específico)
            if (!$isComplete) {
                return response()->json([
                    'message' => 'Chunk processado.',
                    'part_number' => $partNumber,
                    'total_size' => File::exists($tempFilePath) ? File::size($tempFilePath) : 0
                ], 200);
            }

            // =================================================================
            // 4. FINALIZAÇÃO (Disparo do Job)
            // =================================================================

            // Verifica se o arquivo base existe antes de disparar o Job
            $this->validateFinalVideo($tempFilePath, $metadataFile);

            // Log de resumo
            $partsCount = $this->getPartsCount($metadataFile);
            $finalSize = File::size($tempFilePath);

            Log::info("Gravação finalizada - RESUMO", [
                'oitiva_id' => $id,
                'total_parts' => $partsCount,
                'final_size_mb' => round($finalSize / 1024 / 1024, 2),
                'estimated_duration' => round($partsCount * 5, 0) . 's (estimado baseado em chunks)'
            ]);

            // Dispara processamento
            ProcessOitivaVideo::dispatch($oitiva->id, $tempFilePath, $sessionDir);

            Log::info("Gravação finalizada - processamento iniciado", [
                'oitiva_id' => $id,
                'final_size' => File::size($tempFilePath),
                'parts_count' => $this->getPartsCount($metadataFile)
            ]);

            return response()->json([
                'message' => 'Gravação finalizada. O processamento iniciou em segundo plano.',
                'status' => 'processing',
                'final_size' => File::size($tempFilePath)
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => 'Validação falhou',
                'details' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            // Limpeza em caso de erro
            if (isset($sessionDir) && File::isDirectory($sessionDir)) {
                $this->cleanupSession($sessionDir);
            }

            Log::error("Erro crítico no upload - Oitiva #{$id}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Erro interno no upload',
                'message' => config('app.debug') ? $e->getMessage() : 'Tente novamente'
            ], 500);
        }
    }

    /**
     * Inicializa arquivo de metadata
     */
    private function initializeMetadata(string $path, int $oitivaId): void
    {
        $metadata = [
            'oitiva_id' => $oitivaId,
            'started_at' => now()->toIso8601String(),
            'parts' => [],
            'total_size' => 0
        ];

        File::put($path, json_encode($metadata, JSON_PRETTY_PRINT));
    }

    /**
     * Registra parte no metadata
     */
    private function registerPart(string $metadataPath, int $partNumber, int $size): void
    {
        $metadata = json_decode(File::get($metadataPath), true);

        $metadata['parts'][$partNumber] = [
            'number' => $partNumber,
            'size' => $size,
            'received_at' => now()->toIso8601String()
        ];

        $metadata['total_size'] = ($metadata['total_size'] ?? 0) + $size;
        $metadata['last_part'] = $partNumber;

        File::put($metadataPath, json_encode($metadata, JSON_PRETTY_PRINT));
    }

    /**
     * Valida chunk individual
     */
    private function validateChunk($file, string $currentFilePath): void
    {
        // Valida MIME type
        $allowedMimes = ['video/webm', 'video/x-matroska', 'application/octet-stream'];
        if (!in_array($file->getMimeType(), $allowedMimes)) {
            throw new \Exception("Tipo de arquivo inválido: {$file->getMimeType()}");
        }

        // Valida tamanho do chunk
        if ($file->getSize() > self::CHUNK_SIZE_LIMIT) {
            throw new \Exception("Chunk excede limite de " . (self::CHUNK_SIZE_LIMIT / 1024 / 1024) . "MB");
        }

        // Valida tamanho total
        if (File::exists($currentFilePath)) {
            $currentSize = File::size($currentFilePath);
            if (($currentSize + $file->getSize()) > self::MAX_VIDEO_SIZE) {
                throw new \Exception("Vídeo excede limite de " . (self::MAX_VIDEO_SIZE / 1024 / 1024 / 1024) . "GB");
            }
        }

        // Valida se o arquivo é válido
        if (!$file->isValid()) {
            throw new \Exception("Arquivo de chunk inválido");
        }
    }

    /**
     * Append chunk usando streaming (não carrega na memória)
     */
    private function appendChunkStreaming($file, string $targetPath, string $lockFile): void
    {
        // Adquire lock exclusivo
        $lock = fopen($lockFile, 'c');
        if (!flock($lock, LOCK_EX)) {
            fclose($lock);
            throw new \Exception("Não foi possível adquirir lock para escrita");
        }

        try {
            // Abre arquivo de destino em modo append
            $destination = fopen($targetPath, 'ab');
            if (!$destination) {
                throw new \Exception("Não foi possível abrir arquivo de destino");
            }

            // Abre chunk para leitura
            $source = fopen($file->getRealPath(), 'rb');
            if (!$source) {
                fclose($destination);
                throw new \Exception("Não foi possível abrir chunk para leitura");
            }

            // Copia em blocos de 8KB (streaming - não carrega tudo na memória)
            $bufferSize = 8192;
            while (!feof($source)) {
                $buffer = fread($source, $bufferSize);
                if ($buffer === false) {
                    throw new \Exception("Erro ao ler chunk");
                }

                if (fwrite($destination, $buffer) === false) {
                    throw new \Exception("Erro ao escrever no arquivo de destino");
                }
            }

            fclose($source);
            fclose($destination);

        } finally {
            // Libera lock
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /**
     * Valida vídeo final
     */
    private function validateFinalVideo(string $filePath, string $metadataPath): void
    {
        if (!File::exists($filePath)) {
            throw new \Exception("Arquivo de vídeo final não encontrado");
        }

        $fileSize = File::size($filePath);
        if ($fileSize === 0) {
            throw new \Exception("Arquivo de vídeo está vazio");
        }

        // Valida se o tamanho bate com o metadata
        $metadata = json_decode(File::get($metadataPath), true);
        $expectedSize = $metadata['total_size'] ?? 0;

        // Permite 1% de diferença (margem de erro)
        $tolerance = $expectedSize * 0.01;
        if (abs($fileSize - $expectedSize) > $tolerance) {
            Log::warning("Divergência de tamanho detectada", [
                'expected' => $expectedSize,
                'actual' => $fileSize,
                'difference' => abs($fileSize - $expectedSize)
            ]);
        }

        Log::info("Vídeo final validado", [
            'size' => $fileSize,
            'parts' => count($metadata['parts'] ?? [])
        ]);
    }

    /**
     * Obtém contagem de partes
     */
    private function getPartsCount(string $metadataPath): int
    {
        if (!File::exists($metadataPath)) {
            return 0;
        }

        $metadata = json_decode(File::get($metadataPath), true);
        return count($metadata['parts'] ?? []);
    }

    /**
     * Limpeza de sessão
     */
    private function cleanupSession(string $sessionDir): void
    {
        try {
            if (File::isDirectory($sessionDir)) {
                File::deleteDirectory($sessionDir);
                Log::info("Sessão de upload limpa", ['dir' => $sessionDir]);
            }
        } catch (\Exception $e) {
            Log::error("Erro ao limpar sessão", [
                'dir' => $sessionDir,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Oitiva $oitiva)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateOitivaRequest $request, Oitiva $oitiva)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Oitiva $oitiva)
    {
        //
    }
}
