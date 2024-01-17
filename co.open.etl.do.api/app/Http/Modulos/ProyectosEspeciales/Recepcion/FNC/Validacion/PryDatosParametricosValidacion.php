<?php
namespace App\Http\Modulos\ProyectosEspeciales\Recepcion\FNC\Validacion;

use openEtl\Tenant\Models\ProyectosEspeciales\FNC\TenantPryDatosParametricosValidacion;

class PryDatosParametricosValidacion extends TenantPryDatosParametricosValidacion {
    protected $visible= [
        'dpv_id',
        'dpv_clasificacion',
        'dpv_valor',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion'
    ];
}
