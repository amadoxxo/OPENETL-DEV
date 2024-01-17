<?php
namespace App\Http\Modulos\ProyectosEspeciales\Emision\DHLExpress\PickupCash;

use openEtl\Tenant\Models\ProyectosEspeciales\DHLExpress\TenantPryArchivoPickupCash;

class PryArchivoPickupCash extends TenantPryArchivoPickupCash {
    protected $visible = [
        'apc_id',
        'apc_nombre_archivo_original',
        'apc_nombre_archivo_carpeta',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion'
    ];
}
