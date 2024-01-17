<?php
namespace App\Http\Modulos\ProyectosEspeciales\DHLExpress\DocumentosCCO;

use openEtl\Tenant\Models\ProyectosEspeciales\DHLExpress\TenantPryDatoComunPickupCash;

class PryDatoComunPickupCash extends TenantPryDatoComunPickupCash {
    protected $visible = [
        'dcp_id',
        'dcp_descripcion',
        'dcp_tabla',
        'dcp_campo',
        'dcp_opciones',
        'dcp_valor',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion'
    ];
}
