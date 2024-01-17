<?php
namespace App\Http\Modulos\ProyectosEspeciales\DHLExpress\DocumentosCCO;

use openEtl\Tenant\Models\ProyectosEspeciales\DHLExpress\TenantPryCodigoHomologacionPickupCash;

class PryCodigoHomologacionPickupCash extends TenantPryCodigoHomologacionPickupCash {
    protected $visible = [
        'coh_id',
        'coh_extracargo',
        'coh_tipo',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion',
        'getUsuarioCreacion'
    ];
}