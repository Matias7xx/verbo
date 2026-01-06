<?php

namespace App\Models;

use App\TipoOitiva as TipoOitiva;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Oitiva extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'uuid', 'user_id', 'unidade_id', 'declarante_id', 'representante_id',
        'numero_inquerito', 'nome_delegado_responsavel', 'tipo_oitiva',
        'caminho_arquivo_video', 'hash_arquivo_video', 'assinatura_biometrica',
        'data_inicio_gravacao', 'data_fim_gravacao', 'observacoes'
    ];

    protected $casts = [
        'tipo_oitiva' => TipoOitiva::class,
        'data_inicio_gravacao' => 'datetime',
        'data_fim_gravacao' => 'datetime',
    ];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class); // Policial responsável pela gravação
    }

    public function declarante(): BelongsTo
    {
        return $this->belongsTo(Declarante::class);
    }

    public function representante(): BelongsTo
    {
        return $this->belongsTo(Representante::class);
    }

    public function unidade(): BelongsTo
    {
        return $this->belongsTo(Unidade::class);
    }
}