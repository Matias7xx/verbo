<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Representante extends Model
{
    protected $fillable = [
        'nome_completo',
        'cpf',
        'tipo',
        'numero_oab',
        'telefone',
        'email',
    ];
}
