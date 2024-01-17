<?php
namespace App\Http\Modulos\ProyectosEspeciales\Emision\DHLExpress\PickupCash;

use openEtl\Tenant\Models\ProyectosEspeciales\DHLExpress\TenantPryDetalleArchivoPickupCash;

class PryDetalleArchivoPickupCash extends TenantPryDetalleArchivoPickupCash {
    protected $visible = [
        'dpc_id',
        'apc_id',
        'dpc_fecha_guia',
        'dpc_numero_guia',
        'dpc_codigo_estacion',
        'dpc_oficina_venta',
        'dpc_organizacion_ventas',
        'dpc_numero_nota',
        'dpc_importe',
        'dpc_cuenta',
        'dpc_route_code',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion'
    ];
}
