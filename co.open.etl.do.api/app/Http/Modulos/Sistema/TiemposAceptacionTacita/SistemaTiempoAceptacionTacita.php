<?php
namespace App\Http\Modulos\Sistema\TiemposAceptacionTacita;

use openEtl\Main\Models\Sistema\TiemposAceptacionTacita\MainTiempoAceptacionTacita;

class SistemaTiempoAceptacionTacita extends MainTiempoAceptacionTacita {
    protected $visible = [
        'tat_id',
        'tat_codigo',
        'tat_descripcion',
        'tat_segundos',
        'tat_default',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado'
    ];
}