<?php

namespace App;

enum TipoOitiva: string
{
    case DECLARACAO = 'declaracao';
    case INTERROGATORIO = 'interrogatorio';
    case DEPOIMENTO = 'depoimento';
    case RECONHECIMENTO = 'reconhecimento';
    case ACAREACAO = 'acareacao';
}
