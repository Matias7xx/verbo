<?php

namespace App\Http\Controllers;

use App\Models\Oitiva;
use App\Models\AcessoOitiva;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\URL;
use App\Http\Controllers\OitivaController;
use Illuminate\Support\Facades\Storage;
use App\Jobs\ProcessVideoDownloadZip;

class PublicOitivaController extends Controller
{
    // A view da sala de gravação
    public function sala(Request $request, Oitiva $oitiva)
    {
        // Middleware 'signed' já validou a segurança.
        $oitiva->load(['declarante', 'representante']);

        // Precisamos gerar uma URL de upload assinada também,
        // pois a rota de upload exige assinatura para aceitar o arquivo.
        $uploadUrl = URL::temporarySignedRoute(
            'public.oitiva.upload',
            now()->addHours(4),
            ['oitiva' => $oitiva->id]
        );

        return Inertia::render('Oitivas/PublicRecorder', [
            'oitiva' => $oitiva,
            'upload_url' => $uploadUrl
        ]);
    }

    // O método que recebe o POST do vídeo
    public function upload(Request $request, Oitiva $oitiva)
    {
        // Instancia o controller principal para reutilizar a lógica robusta de chunks
        // que já criamos (com tratamento de php.ini, temp files, S3, Jobs)
        $mainController = new OitivaController(new \App\Services\OitivaService());

        return $mainController->uploadVideoChunk($request, $oitiva->id);
    }

    public function assistir(Request $request, Oitiva $oitiva)
    {
        // 1. Validação dos Parâmetros Obrigatórios (Query String)
        // Como a rota é 'signed', garantimos que quem gerou o link incluiu esses dados
        $request->validate([
            'nome' => 'required|string',
            'matricula' => 'required|string',
        ]);

        // 2. Verificação se o vídeo existe
        if (empty($oitiva->caminho_arquivo_video)) {
            abort(404, 'O vídeo desta oitiva ainda não foi processado ou não existe.');
        }

        // 3. Registrar Log de Acesso
        // Usamos firstOrCreate para evitar duplicar logs se o usuário der F5 na mesma sessão rápida,
        // ou create() direto se quiser logar CADA refresh (recomendado para auditoria rigorosa).
        AcessoOitiva::create([
            'oitiva_id' => $oitiva->id,
            'nome_servidor' => $request->query('nome'),
            'matricula_servidor' => $request->query('matricula'),
            'tipo_acesso' => 'visualizacao',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        // 4. Gerar URL Temporária do Vídeo (Válida por 1 hora)
        $urlVideo = \App\Helpers\StorageHelper::getPublicUrl(
                $oitiva->caminho_arquivo_video,
                60
            );

        // 5. Renderizar a View
        return Inertia::render('Oitivas/PublicPlayer', [
            'oitiva' => $oitiva->load('declarante'),
            'url_video' => $urlVideo,
            'viewer_info' => [
                'nome' => $request->query('nome'),
                'matricula' => $request->query('matricula')
            ]
        ]);
    }

    public function iniciarDownload(Request $request, Oitiva $oitiva)
{
    // Verifica se o vídeo existe
    if (empty($oitiva->caminho_arquivo_video)) {
        return response()->json([
            'error' => 'Vídeo não disponível para download'
        ], 404);
    }

    // Verifica se já está processando
    if ($oitiva->status_download === 'processing') {
        return response()->json([
            'message' => 'Download já está sendo processado',
            'status' => 'processing'
        ]);
    }

    // Verifica se já foi processado
    if ($oitiva->status_download === 'completed' && $oitiva->download_zip_path) {
        // Verifica se o arquivo ainda existe
        if (Storage::exists($oitiva->download_zip_path)) {
            return response()->json([
                'message' => 'Download já está pronto',
                'status' => 'completed'
            ]);
        }
    }

    // Dispara o Job para processar
    ProcessVideoDownloadZip::dispatch($oitiva->id);

    return response()->json([
        'message' => 'Processamento iniciado',
        'status' => 'processing'
    ]);
}

/**
 * Verifica o status do processamento do download
 */
public function statusDownload(Oitiva $oitiva)
{
    return response()->json([
        'status' => $oitiva->status_download ?? 'pending',
        'ready' => $oitiva->status_download === 'completed'
    ]);
}

/**
 * Faz o download do arquivo ZIP
 */
public function downloadZip(Oitiva $oitiva)
{
    // Verifica se o processamento foi concluído
    if ($oitiva->status_download !== 'completed' || empty($oitiva->download_zip_path)) {
        return response()->json([
            'error' => 'Download não está pronto ainda'
        ], 404);
    }

    // Verifica se o arquivo existe
    $zipPath = storage_path('app/' . $oitiva->download_zip_path);
    if (!file_exists($zipPath)) {
        return response()->json([
            'error' => 'Arquivo não encontrado'
        ], 404);
    }

    // Retorna o arquivo para download
    return response()->download($zipPath, $oitiva->uuid . '.zip');
}
}
