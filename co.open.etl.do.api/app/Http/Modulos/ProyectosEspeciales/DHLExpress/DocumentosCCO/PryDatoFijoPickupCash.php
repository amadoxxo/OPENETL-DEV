<?php
namespace App\Http\Modulos\ProyectosEspeciales\DHLExpress\DocumentosCCO;

use openEtl\Tenant\Models\ProyectosEspeciales\DHLExpress\TenantPryDatoFijoPickupCash;

class PryDatoFijoPickupCash extends TenantPryDatoFijoPickupCash {
    protected $visible = [
        'dfp_id',
        'dfp_descripcion',
        'dfp_valor',
        'dfp_edicion',
        'dfp_opciones',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion'
    ];
}
