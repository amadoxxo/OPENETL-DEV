<?php
namespace App\Http\Modulos\ProyectosEspeciales\DHLExpress\DocumentosCCO;

use openEtl\Tenant\Models\ProyectosEspeciales\DHLExpress\TenantPryDatoVariablePickupCash;

class PryDatoVariablePickupCash extends TenantPryDatoVariablePickupCash {
    protected $visible = [
        'dvp_id',
        'dvp_descripcion',
        'dvp_valor',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion'
    ];
}
