<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Storage;
use Aws\S3\S3Client;

class StorageHelper
{
    /**
     * Gera URL pública temporária que funciona em desenvolvimento E produção
     *
     * @param string $path Caminho do arquivo no S3/MinIO
     * @param int $minutesValid Tempo de validade em minutos
     * @return string URL assinada acessível publicamente
     */
    public static function getPublicUrl(string $path, int $minutesValid = 60): string
    {
        $publicEndpoint = config('filesystems.disks.s3.url')
            ?: config('filesystems.disks.s3.endpoint');

        // Cria um cliente S3 temporário com endpoint público
        $s3Client = new S3Client([
            'version' => 'latest',
            'region' => config('filesystems.disks.s3.region'),
            'endpoint' => $publicEndpoint,
            'use_path_style_endpoint' => true,
            'credentials' => [
                'key' => config('filesystems.disks.s3.key'),
                'secret' => config('filesystems.disks.s3.secret'),
            ],
        ]);

        // Gera comando GetObject
        $command = $s3Client->getCommand('GetObject', [
            'Bucket' => config('filesystems.disks.s3.bucket'),
            'Key' => $path,
        ]);

        // Cria URL pré-assinada válida
        $request = $s3Client->createPresignedRequest(
            $command,
            "+{$minutesValid} minutes"
        );

        return (string) $request->getUri();
    }
}
