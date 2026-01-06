<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcessoOitiva extends Model
{
    protected $fillable = [
        'oitiva_id',
        'nome_servidor',
        'matricula_servidor',
        'tipo_acesso',
        'ip_address',
        'user_agent'
    ];

    public function oitiva(): BelongsTo
    {
        return $this->belongsTo(Oitiva::class);
    }
}
