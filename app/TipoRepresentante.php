<?php

namespace App;

enum TipoRepresentante: string
{
    case ADVOGADO = 'advogado';
    case CURADOR = 'curador';
    case PAI_MAE = 'ascendente';
}
