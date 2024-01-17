<?php
namespace App\Http\Modulos\ProyectosEspeciales\DHLExpress\FechasBassware;

use openEtl\Tenant\Models\ProyectosEspeciales\DHLExpress\TenantPryFechasBassware;

class PryFechasBassware extends TenantPryFechasBassware {
    protected $visible = [
        'fcb_id',
        'fcb_periodo',
        'fcb_fecha_apertura',
        'fcb_fecha_cierre',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion'
    ];
}