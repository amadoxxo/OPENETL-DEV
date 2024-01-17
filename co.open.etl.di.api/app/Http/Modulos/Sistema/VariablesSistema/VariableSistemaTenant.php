<?php

namespace App\Http\Modulos\Sistema\VariablesSistema;

use openEtl\Tenant\Models\Sistema\VariablesSistema\TenantVariableSistema;

class VariableSistemaTenant extends TenantVariableSistema {
    protected $visible = [
        'vsi_id', 
        'vsi_nombre',
        'vsi_valor',
        'vsi_descripcion',
        'vsi_ejemplo',
        'vsi_bases_datos',
        'usuario_creacion', 
        'fecha_creacion',
        'fecha_modificacion',
        'estado'
    ];
}
