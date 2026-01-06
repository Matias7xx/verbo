<?php

namespace App\Services;

use App\Models\Oitiva;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Exception;

class OitivaService
{
    /**
     * Recebe um registro de oitiva já existente (apenas metadados) 
     * e anexa o arquivo de vídeo, garantindo a integridade (hash).
     *
     * @param Oitiva $oitiva O registro criado previamente no banco
     * @param UploadedFile $videoArquivo O arquivo enviado pelo upload
     * @return Oitiva
     * @throws Exception
     */
    public function anexarVideoAoRegistroExistente(Oitiva $oitiva, UploadedFile $videoArquivo): Oitiva
    {
        // 1. Integridade (Cadeia de Custódia)
        // Calculamos o hash do arquivo físico que está na pasta temporária do PHP (/tmp)
        // Isso é feito ANTES de enviar para a nuvem para garantir que é exatamente este arquivo.
        $hashSha256 = hash_file('sha256', $videoArquivo->getRealPath());

        if (!$hashSha256) {
            throw new Exception("Falha crítica: Não foi possível calcular o Hash de integridade do vídeo.");
        }

        // 2. Definição do Caminho no Storage (S3/MinIO)
        // Estrutura: oitivas/ANO/MES/UUID_DA_OITIVA.extensão
        // Usar o UUID da oitiva no nome do arquivo facilita a auditoria forense.
        $extensao = $videoArquivo->getClientOriginalExtension();
        $caminhoRelativo = sprintf(
            'oitivas/%s/%s/%s.%s',
            date('Y'),
            date('m'),
            $oitiva->uuid, 
            $extensao
        );

        // 3. Upload (Streaming para o MinIO)
        try {
            // putFileAs gerencia o streaming, evitando estourar a memória RAM com vídeos grandes
            $pathStorage = Storage::disk('s3')->putFileAs(
                dirname($caminhoRelativo), // Pasta (oitivas/2025/12)
                $videoArquivo,             // O objeto Arquivo
                basename($caminhoRelativo) // Nome do arquivo (uuid.mp4)
            );

            if (!$pathStorage) {
                throw new Exception("O driver de armazenamento não retornou o caminho esperado.");
            }

        } catch (Exception $e) {
            // Logar erro interno se necessário: Log::error($e->getMessage());
            throw new Exception("Falha ao enviar o vídeo para o armazenamento seguro (S3/MinIO).");
        }

        // 4. Atualizar o Registro no Banco
        // Agora que o arquivo está seguro, vinculamos ele ao registro.
        $oitiva->update([
            'caminho_arquivo_video' => $caminhoRelativo,
            'hash_arquivo_video'    => $hashSha256,
            // Assumimos que a gravação acabou no momento do upload, 
            // mas você pode ajustar isso se tiver metadados vindos do front.
            'data_fim_gravacao'     => now(), 
        ]);

        return $oitiva;
    }
}