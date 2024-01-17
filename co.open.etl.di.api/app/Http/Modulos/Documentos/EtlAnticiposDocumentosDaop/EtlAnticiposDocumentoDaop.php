<?php
namespace App\Http\Modulos\Documentos\EtlAnticiposDocumentosDaop;

use openEtl\Tenant\Models\Documentos\EtlAnticiposDocumentosDaop\TenantEtlAnticipoDocumentoDaop;

/**
 * Clase gestora de Anticipos.
 *
 * Class EtlAnticiposDocumentosDaop
 * @package App\Http\Modulos\Documentos\EtlAnticiposDocumentosDaop
 */
class EtlAnticiposDocumentoDaop extends TenantEtlAnticipoDocumentoDaop {
    protected $visible = [
        'cdo_id',
        'ant_identificacion',
        'ant_valor',
        'ant_valor_moneda_extranjera',
        'ant_fecha_recibido',
        'ant_fecha_realizado',
        'ant_hora_realizado',
        'ant_instrucciones',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion'
    ];
}