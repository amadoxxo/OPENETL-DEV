<?php
namespace App\Http\Modulos\ProyectosEspeciales\DHLExpress\CorreosNotificacionBasware;

use openEtl\Tenant\Models\ProyectosEspeciales\DHLExpress\TenantPryCorreoNotificacionBasware;

class PryCorreoNotificacionBasware extends TenantPryCorreoNotificacionBasware {
    protected $visible = [
        'cnb_id',
        'pro_identificacion',
        'cnb_correo_proveedor',
        'cnb_correo_interno',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion'
    ];
}