<?php

namespace App\Http\Modulos\Sistema\VariablesSistema;

use openEtl\Main\Models\Sistema\VariablesSistema\MainVariableSistema;

class VariableSistema extends MainVariableSistema {
    protected $visible = [
        'vsi_id', 
        'vsi_nombre',
        'vsi_valor',
        'vsi_descripcion',
        'vsi_ejemplo',
        'usuario_creacion', 
        'fecha_creacion',
        'fecha_modificacion',
        'estado'
    ];
}