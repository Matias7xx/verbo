<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Declarante extends Model
{
    use SoftDeletes;

    protected $fillable = ['nome_completo', 'cpf', 'rg', 'data_nascimento', 'nome_mae', 'telefone'];

    public function oitivas(): HasMany
    {
        return $this->hasMany(Oitiva::class);
    }
}
