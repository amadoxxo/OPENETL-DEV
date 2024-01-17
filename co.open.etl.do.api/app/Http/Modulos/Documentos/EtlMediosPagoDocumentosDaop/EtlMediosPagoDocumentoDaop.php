<?php

namespace App\Http\Modulos\Documentos\EtlMediosPagoDocumentosDaop;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Http\Modulos\Parametros\FormasPago\ParametrosFormaPago;
use App\Http\Modulos\Parametros\MediosPago\ParametrosMediosPago;
use openEtl\Tenant\Models\Documentos\EtlMediosPagoDocumentosDaop\TenantEtlMedioPagoDocumentoDaop;

/**
 * Clase gestora de medios de pago de documentos electrónicos
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
        'fecha_actualizacion',
        'getMedioPago',
        'getFormaPago'
    ];

    // INICIO RELACIONES
    /**
     * Retorna el medio de pago del documento.
     *
     * @return BelongsTo
     */
    public function getMedioPago() {
        return $this->belongsTo(ParametrosMediosPago::class, 'mpa_id')->select(['mpa_id', 'mpa_codigo', 'mpa_descripcion']);
    }

    /**
     * Retorna la forma de pago del documento.
     *
     * @return BelongsTo
     */
    public function getFormaPago() {
        return $this->belongsTo(ParametrosFormaPago::class, 'fpa_id')->select(['fpa_id', 'fpa_codigo', 'fpa_descripcion']);
    }
    // FIN RELACIONES
}