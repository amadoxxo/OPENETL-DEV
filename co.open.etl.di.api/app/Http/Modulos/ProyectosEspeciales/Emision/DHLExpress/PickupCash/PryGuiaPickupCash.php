<?php
namespace App\Http\Modulos\ProyectosEspeciales\Emision\DHLExpress\PickupCash;

use openEtl\Tenant\Models\ProyectosEspeciales\DHLExpress\TenantPryGuiaPickupCash;

class PryGuiaPickupCash extends TenantPryGuiaPickupCash {
    protected $visible = [
        'gpc_id',
        'gpc_interfaz',
        'gpc_fecha_factura',
        'gpc_fecha_generacion_awb',
        'gpc_cuenta_cliente',
        'gpc_paquete_documento',
        'gpc_guia',
        'gpc_area_servicio',
        'gpc_codigo_estacion',
        'gpc_oficina_venta',
        'gpc_organizacion_ventas',
        'gpc_numero_externo',
        'gpc_numero_nota',
        'gpc_estacion_origen',
        'gpc_estacion_destino',
        'gpc_texto_final',
        'gpc_importe_total',
        'gpc_route_code',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion'
    ];
}
