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
    public function __construct(
        protected OitivaService $oitivaService
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // Busca oitivas do usuário logado, ordenadas da mais recente
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
        // Busca apenas usuários com cargo de Delegado
        // Ajuste 'cargo' conforme sua estrutura real (se é string ou ID)
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
            $urlVideo = Storage::disk('s3')->temporaryUrl(
                $oitiva->caminho_arquivo_video,
                now()->addMinutes(60)
            );
        }

        return Inertia::render('Oitivas/Show', [
            'oitiva' => $oitiva,
            'url_video' => $urlVideo // Passamos a URL pronta para o front
        ]);
    }

    public function uploadVideoChunk(Request $request, $id)
    {
        // 1. Alteração na Validação: 'nullable' em vez de 'required'
        $request->validate([
            // 'video_part' => ['nullable', 'file', 'mimetypes:video/webm,video/x-matroska,application/octet-stream'],
            'part_number' => 'required|integer',
            'is_recording_complete' => 'required|boolean',
        ]);

        try {
            // 2. Inspeção Manual do Arquivo
            if ($request->hasFile('video_part')) {
                $file = $request->file('video_part');

                // Validação manual de MIME Type (substitui a regra do Laravel)
                $allowedMimes = ['video/webm', 'video/x-matroska', 'application/octet-stream'];
                if (!in_array($file->getMimeType(), $allowedMimes)) {
                    return response()->json(['error' => "Tipo de arquivo inválido: " . $file->getMimeType()], 422);
                }
            }
            
            $oitiva = Oitiva::findOrFail($id);
            $isComplete = $request->boolean('is_recording_complete');
            
            // Caminho temporário (igual ao anterior)
            $tempDir = storage_path('app/temp_chunks');
            $tempFileName = $oitiva->uuid . '.webm';
            $tempFilePath = $tempDir . '/' . $tempFileName;

            if (!File::isDirectory($tempDir)) {
                File::makeDirectory($tempDir, 0755, true);
            }

            // 2. Lógica Condicional: Só tenta salvar se enviou arquivo
            if ($request->hasFile('video_part')) {
                $fileChunk = $request->file('video_part');
                
                // Verifica se o arquivo é válido antes de tentar ler
                if ($fileChunk->isValid()) {
                    $content = file_get_contents($fileChunk->getRealPath());
                    if (file_put_contents($tempFilePath, $content, FILE_APPEND) === false) {
                        throw new \Exception('Falha ao escrever o chunk no disco temporário.');
                    }
                }
            }

            // 3. Se NÃO acabou, retorna sucesso (mesmo se não veio arquivo neste chunk específico)
            if (!$isComplete) {
                return response()->json(['message' => 'Chunk processado.'], 200);
            }

            // =================================================================
            // 4. FINALIZAÇÃO (Disparo do Job)
            // =================================================================
            
            // Verifica se o arquivo base existe antes de disparar o Job
            if (!File::exists($tempFilePath)) {
                return response()->json(['error' => 'Arquivo de vídeo base não encontrado no servidor.'], 404);
            }

            // Dispara o Job que criamos (ProcessOitivaVideo)
            // Certifique-se de importar: use App\Jobs\ProcessOitivaVideo;
            ProcessOitivaVideo::dispatch($oitiva->id, $tempFilePath);

            return response()->json([
                'message' => 'Gravação finalizada. O processamento iniciou em segundo plano.',
                'status' => 'processing'
            ], 200);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Erro Upload Chunk #{$id}: " . $e->getMessage());
            return response()->json(['error' => 'Erro interno: ' . $e->getMessage()], 500);
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
