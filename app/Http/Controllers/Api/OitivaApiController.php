<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Oitiva;
use App\Models\Declarante;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\DB;

class OitivaApiController extends Controller
{
    public function store(Request $request)
    {
        // O sistema externo envia TODOS os dados necessários
        $validated = $request->validate([
            'numero_inquerito' => 'required|string',
            'tipo_oitiva' => 'required|string', // Interrogatório, Depoimento...
            'nome_delegado' => 'required|string', // Quem preside
            'nome_agente' => 'required|string',   // Quem está operando (já que não há login)
            'declarante_nome' => 'required|string',
            'declarante_cpf' => 'nullable|string',
            // ... outros campos (representante, etc)
        ]);

        try {
            $oitiva = DB::transaction(function () use ($request, $validated) {
                // 1. Cria ou recupera Declarante
                $declarante = Declarante::firstOrCreate(
                    ['cpf' => $request->declarante_cpf],
                    ['nome_completo' => $request->declarante_nome]
                );

                // 2. Cria Oitiva
                // O user_id será o ID do "Sistema Externo" (token sanctum) ou nulo se permitido.
                // Mas salvamos o nome do agente/delegado nos campos de texto.
                return Oitiva::create([
                    'user_id' => $request->user()->id, // O usuário da API (Sistema Integração)
                    'unidade_id' => $request->user()->unidade_id, // Unidade vinculada ao token
                    'declarante_id' => $declarante->id,
                    'numero_inquerito' => $validated['numero_inquerito'],
                    'tipo_oitiva' => $validated['tipo_oitiva'],
                    'nome_delegado_responsavel' => $validated['nome_delegado'],
                    'observacoes' => "Oitiva realizada por: " . $validated['nome_agente'],
                ]);
            });

            // 3. Gera URL de Gravação (Válida por 4 horas)
            $urlGravacao = URL::temporarySignedRoute(
                'public.oitiva.sala', 
                now()->addHours(4),
                ['oitiva' => $oitiva->id]
            );

            // 4. Gera URL de Visualização Futura (Link permanente ou assinado)
            // Aqui mandamos o link para o sistema externo guardar
            $urlVisualizacao = route('public.oitiva.assistir', ['oitiva' => $oitiva->uuid]);

            return response()->json([
                'status' => 'success',
                'sala_id' => $oitiva->uuid,
                'links' => [
                    'acesso_gravacao' => $urlGravacao,     // Abrir AGORA
                    'acesso_video_final' => $urlVisualizacao // Guardar para DEPOIS
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Erro ao criar sala: ' . $e->getMessage()], 500);
        }
    }

    public function refreshLink(Request $request, $uuid)
    {
        try {
            // 1. Busca a Oitiva pelo UUID
            // Usamos firstOrFail para retornar 404 se não achar
            $oitiva = Oitiva::where('uuid', $uuid)->firstOrFail();

            // 2. CONDIÇÃO DE BLOQUEIO: Oitiva já gravada
            // Se já existe um vídeo, não faz sentido gerar link para gravar de novo.
            if (!empty($oitiva->caminho_arquivo_video)) {
                return response()->json([
                    'status' => 'error',
                    'code' => 'OITIVA_ALREADY_RECORDED',
                    'message' => 'Esta oitiva já foi gravada e finalizada. Não é permitido gerar novos links de gravação.',
                    'hint' => 'Utilize a rota de visualização/assistir para obter acesso ao vídeo.'
                ], 409); // 409 Conflict
            }

            // 3. Validação de Regra de Negócio (Opcional)
            // Se a oitiva já tem vídeo gravado, talvez não devêssemos gerar link de gravação novo
            if ($oitiva->caminho_arquivo_video) {
                return response()->json([
                    'error' => 'Esta oitiva já foi finalizada e possui vídeo gravado. Não é possível gerar novo link de gravação.'
                ], 409); // Conflict
            }

            // 4. Gerar NOVA URL Assinada (Renova por mais 4 horas a partir de AGORA)
            $novoLinkGravacao = URL::temporarySignedRoute(
                'public.oitiva.sala', 
                now()->addHours(4),
                ['oitiva' => $oitiva->id]
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Link renovado com sucesso.',
                'data' => [
                    'oitiva_uuid' => $oitiva->uuid,
                    'novo_link_acesso' => $novoLinkGravacao,
                    'expira_em' => now()->addHours(4)->toIso8601String()
                ]
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Oitiva não encontrada.'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Erro interno ao renovar link.'], 500);
        }
    }

    public function generateWatchLink(Request $request, $uuid)
    {
        // 1. Validação dos dados do servidor que vai assistir
        $validated = $request->validate([
            'nome_servidor' => 'required|string',
            'matricula_servidor' => 'required|string',
            'validade_minutos' => 'nullable|integer|min:10|max:1440' // Opcional, padrão 60 min
        ]);

        try {
            // 2. Busca a Oitiva
            $oitiva = Oitiva::where('uuid', $uuid)->firstOrFail();

            // 3. Validação: O vídeo existe?
            if (empty($oitiva->caminho_arquivo_video)) {
                return response()->json([
                    'error' => 'O vídeo desta oitiva ainda não está disponível ou não foi processado.'
                ], 404);
            }

            // 4. Define o tempo de expiração (Padrão: 60 minutos)
            $tempoValidade = now()->addMinutes($request->input('validade_minutos', 60));

            // 5. GERA A URL MÁGICA
            // O Laravel pega os parâmetros extras array e transforma em Query String
            $urlAssistir = URL::temporarySignedRoute(
                'public.oitiva.assistir', // Nome da rota Web que criamos antes
                $tempoValidade,
                [
                    'oitiva' => $oitiva->id, // Parâmetro de rota (obrigatório na definição da rota)
                    
                    // Parâmetros de Query String (serão validados pela assinatura)
                    'nome' => $validated['nome_servidor'],
                    'matricula' => $validated['matricula_servidor']
                ]
            );

            return response()->json([
                'status' => 'success',
                'data' => [
                    'oitiva_uuid' => $oitiva->uuid,
                    'link_assistir' => $urlAssistir,
                    'expira_em' => $tempoValidade->toIso8601String(),
                    'auditoria' => [
                        'servidor' => $validated['nome_servidor'],
                        'matricula' => $validated['matricula_servidor']
                    ]
                ]
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Oitiva não encontrada.'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Erro interno ao gerar link: ' . $e->getMessage()], 500);
        }
    }
}