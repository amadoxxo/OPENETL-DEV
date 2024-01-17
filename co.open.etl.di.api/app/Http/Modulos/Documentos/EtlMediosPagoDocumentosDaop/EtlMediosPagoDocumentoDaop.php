<?php

namespace App\Http\Modulos\Documentos\EtlMediosPagoDocumentosDaop;

use openEtl\Tenant\Models\Documentos\EtlMediosPagoDocumentosDaop\TenantEtlMedioPagoDocumentoDaop;

/**
 * Clase gestora de medios de pago de documentos electronicos
 *
 * Class EtlMediosPagoDocumentosDaop
 * @package App\Http\Modulos\Documentos\EtlMediosPagoDocumentosDaop
 */
class EtlMediosPagoDocumentoDaop extends TenantEtlMedioPagoDocumentoDaop
{
    protected $visible= [
        'men_id',
        'cdo_id',
        'fpa_id',
        'mpa_id',
        'men_fecha_vencimiento',
        'men_identificador_pago',
        'mpa_informacion_adicional',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion'
    ];
}